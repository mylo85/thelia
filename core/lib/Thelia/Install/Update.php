<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Install;

use PDO;
use PDOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Thelia;
use Thelia\Install\Exception\UpdateException;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Log\Tlog;

/**
 * Class Update
 * @package Thelia\Install
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Update
{
    const SQL_DIR = 'update/sql/';

    const PHP_DIR = 'update/php/';

    protected static $version = array(
        '0' => '2.0.0-beta1',
        '1' => '2.0.0-beta2',
        '2' => '2.0.0-beta3',
        '3' => '2.0.0-beta4',
        '4' => '2.0.0-RC1',
        '5' => '2.0.0',
        '6' => '2.0.1',
        '7' => '2.0.2',
        '8' => '2.0.3-beta',
        '9' => '2.0.3-beta2',
        '10' => '2.0.3',
        '11' => '2.0.4',
        '12' => '2.0.5',
        '13' => '2.1.0-alpha1',
        '14' => '2.1.0-alpha2',
        '15' => '2.1.0-beta1',
        '16' => '2.1.0-beta2',
        '17' => '2.1.0',
        '18' => '2.1.1',
    );

    /** @var bool */
    protected $usePropel = null;

    /** @var null|Tlog */
    protected $logger = null;

    /** @var array log messages */
    protected $logs = [];

    /** @var array */
    protected $updatedVersions = [];

    /** @var PDO  */
    protected $connection = null;

    /** @var string|null  */
    protected $backupFile = null;

    /** @var string */
    protected $backupDir = 'local/backup/';

    public function __construct($usePropel = true)
    {
        $this->usePropel = $usePropel;

        if ($this->usePropel) {
            $this->logger = Tlog::getInstance();
            $this->logger->setLevel(Tlog::DEBUG);
        } else {
            $this->logs = [];
        }

        $dbConfig = null;

        $configPath = THELIA_ROOT . "/local/config/database.yml";

        if (!file_exists($configPath)) {
            throw new UpdateException("Thelia is not installed yet");
        }

        try {
            $dbConfig = Yaml::parse($configPath);
            $dbConfig = $dbConfig['database']['connection'];
        } catch (ParseException $ex) {
            throw new UpdateException("database.yml is not a valid file : " . $ex->getMessage());
        }

        try {
            $this->connection = new \PDO(
                $dbConfig['dsn'],
                $dbConfig['user'],
                $dbConfig['password']
            );
        } catch (\PDOException $ex) {
            throw new UpdateException('Wrong connection information' . $ex->getMessage());
        }
    }

    public function isLatestVersion($version = null)
    {
        if (null === $version) {
            $version = $this->getCurrentVersion();
        }
        $lastEntry = end(self::$version);

        return $lastEntry == $version;
    }

    public function process()
    {
        $this->updatedVersions = array();

        $currentVersion = $this->getCurrentVersion();
        $this->log('debug', "start update process");

        if (true === $this->isLatestVersion($currentVersion)) {
            $this->log('debug', "You already have the latest version. No update available");
            throw new UpToDateException('You already have the latest version. No update available');
        }

        $index = array_search($currentVersion, self::$version);

        $this->connection->beginTransaction();

        $database = new Database($this->connection);
        $version = null;

        try {
            $size = count(self::$version);

            for ($i = ++$index; $i < $size; $i++) {
                $version = self::$version[$i];
                $this->updateToVersion($version, $database);
                $this->updatedVersions[] = $version;
            }

            $this->connection->commit();
            $this->log('debug', 'update successfully');
        } catch (\Exception $e) {
            $this->connection->rollBack();

            $this->log('error', sprintf('error during update process with message : %s', $e->getMessage()));

            $ex = new UpdateException($e->getMessage(), $e->getCode(), $e->getPrevious());
            $ex->setVersion($version);
            throw $ex;
        }
        $this->log('debug', 'end of update processing');

        return $this->updatedVersions;
    }


    /**
     * Backup current DB to file local/backup/update.sql
     *
     * @return bool if it succeeds, false otherwise
     */
    public function backupDb()
    {
        $database = new Database($this->connection);

        $this->backupFile = THELIA_ROOT . $this->backupDir . 'update.sql';
        $backupDir = THELIA_ROOT . $this->backupDir;

        $fs = new Filesystem();

        try {
            $this->log('debug', sprintf('Backup database to file : %s', $this->backupFile));

            // test if backup dir exists
            if (!$fs->exists($backupDir)) {
                $fs->mkdir($backupDir);
            }

            if (!is_writable($backupDir)) {
                throw new \RuntimeException(sprintf('impossible to write in directory : %s', $backupDir));
            }

            // test if backup file already exists
            if ($fs->exists($this->backupFile)) {
                // remove file
                $fs->remove($this->backupFile);
            }

            $database->backupDb($this->backupFile);
        } catch (\Exception $ex) {
            $this->log('error', sprintf('error during backup process with message : %s', $ex->getMessage()));
            throw $ex;
        }
    }

    /**
     * Restores file local/backup/update.sql to current DB
     *
     * @return bool if it succeeds, false otherwise
     */
    public function restoreDb()
    {
        $database = new Database($this->connection);

        try {
            $this->log('debug', sprintf('Restore database with file : %s', $this->backupFile));

            if (!file_exists($this->backupFile)) {
                return false;
            }

            $database->restoreDb($this->backupFile);
        } catch (\Exception $ex) {
            $this->log('error', sprintf('error during restore process with message : %s', $ex->getMessage()));
            print $ex->getMessage();
            return false;
        }

        return true;
    }

    /**
     * @return null|string
     */
    public function getBackupFile()
    {
        return $this->backupFile;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    protected function log($level, $message)
    {
        if ($this->usePropel) {
            switch ($level) {
                case 'debug':
                    $this->logger->debug($message);
                    break;
                case 'info':
                    $this->logger->info($message);
                    break;
                case 'notice':
                    $this->logger->notice($message);
                    break;
                case 'warning':
                    $this->logger->warning($message);
                    break;
                case 'error':
                    $this->logger->error($message);
                    break;
                case 'critical':
                    $this->logger->critical($message);
                    break;
            }
        } else {
            $this->logs[] = [$level, $message];
        }
    }

    protected function updateToVersion($version, Database $database)
    {
        // sql update
        $filename = sprintf(
            "%s%s%s",
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::SQL_DIR),
            $version . '.sql'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('inserting file %s', $version . '.sql'));
            $database->insertSql(null, [$filename]);
            $this->log('debug', sprintf('end inserting file %s', $version . '.sql'));
        }

        // php update
        $filename = sprintf(
            "%s%s%s",
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::PHP_DIR),
            $version . '.php'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('executing file %s', $version . '.php'));
            include_once $filename;
            $this->log('debug', sprintf('end executing file %s', $version . '.php'));
        }

        $this->setCurrentVersion($version);
    }

    public function getCurrentVersion()
    {
        return Thelia::THELIA_VERSION;
    }

    public function setCurrentVersion($version)
    {
        $currentVersion = null;

        if (null !== $this->connection) {
            try {
                $stmt = $this->connection->prepare('UPDATE config set value = ? where name = ?');
                $stmt->execute([$version, 'thelia_version']);
            } catch (PDOException $e) {
                $this->log('error', sprintf('Error setting current version : %s', $e->getMessage()));

                throw $e;
            }
        }
    }

    public function getLatestVersion()
    {
        return end(self::$version);
    }

    public function getVersions()
    {
        return self::$version;
    }

    /**
     * @return array
     */
    public function getUpdatedVersions()
    {
        return $this->updatedVersions;
    }

    /**
     * @param array $updatedVersions
     */
    public function setUpdatedVersions($updatedVersions)
    {
        $this->updatedVersions = $updatedVersions;
    }
}
