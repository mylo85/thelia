<?php

namespace Thelia\Model\Base;

use \DateTime;
use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Thelia\Model\Content as ChildContent;
use Thelia\Model\ContentFolder as ChildContentFolder;
use Thelia\Model\ContentFolderQuery as ChildContentFolderQuery;
use Thelia\Model\ContentQuery as ChildContentQuery;
use Thelia\Model\Document as ChildDocument;
use Thelia\Model\DocumentQuery as ChildDocumentQuery;
use Thelia\Model\Folder as ChildFolder;
use Thelia\Model\FolderI18n as ChildFolderI18n;
use Thelia\Model\FolderI18nQuery as ChildFolderI18nQuery;
use Thelia\Model\FolderQuery as ChildFolderQuery;
use Thelia\Model\FolderVersion as ChildFolderVersion;
use Thelia\Model\FolderVersionQuery as ChildFolderVersionQuery;
use Thelia\Model\Image as ChildImage;
use Thelia\Model\ImageQuery as ChildImageQuery;
use Thelia\Model\Rewriting as ChildRewriting;
use Thelia\Model\RewritingQuery as ChildRewritingQuery;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\FolderVersionTableMap;

abstract class Folder implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\FolderTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the parent field.
     * @var        int
     */
    protected $parent;

    /**
     * The value for the link field.
     * @var        string
     */
    protected $link;

    /**
     * The value for the visible field.
     * @var        int
     */
    protected $visible;

    /**
     * The value for the position field.
     * @var        int
     */
    protected $position;

    /**
     * The value for the created_at field.
     * @var        string
     */
    protected $created_at;

    /**
     * The value for the updated_at field.
     * @var        string
     */
    protected $updated_at;

    /**
     * The value for the version field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $version;

    /**
     * The value for the version_created_at field.
     * @var        string
     */
    protected $version_created_at;

    /**
     * The value for the version_created_by field.
     * @var        string
     */
    protected $version_created_by;

    /**
     * @var        ObjectCollection|ChildImage[] Collection to store aggregation of ChildImage objects.
     */
    protected $collImages;
    protected $collImagesPartial;

    /**
     * @var        ObjectCollection|ChildDocument[] Collection to store aggregation of ChildDocument objects.
     */
    protected $collDocuments;
    protected $collDocumentsPartial;

    /**
     * @var        ObjectCollection|ChildRewriting[] Collection to store aggregation of ChildRewriting objects.
     */
    protected $collRewritings;
    protected $collRewritingsPartial;

    /**
     * @var        ObjectCollection|ChildContentFolder[] Collection to store aggregation of ChildContentFolder objects.
     */
    protected $collContentFolders;
    protected $collContentFoldersPartial;

    /**
     * @var        ObjectCollection|ChildFolderI18n[] Collection to store aggregation of ChildFolderI18n objects.
     */
    protected $collFolderI18ns;
    protected $collFolderI18nsPartial;

    /**
     * @var        ObjectCollection|ChildFolderVersion[] Collection to store aggregation of ChildFolderVersion objects.
     */
    protected $collFolderVersions;
    protected $collFolderVersionsPartial;

    /**
     * @var        ChildContent[] Collection to store aggregation of ChildContent objects.
     */
    protected $collContents;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_EN';

    /**
     * Current translation objects
     * @var        array[ChildFolderI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $imagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $documentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $rewritingsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentFoldersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $folderI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $folderVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Folder object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !empty($this->modifiedColumns);
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return in_array($col, $this->modifiedColumns);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return array_unique($this->modifiedColumns);
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (Boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (Boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            while (false !== ($offset = array_search($col, $this->modifiedColumns))) {
                array_splice($this->modifiedColumns, $offset, 1);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>Folder</code> instance.  If
     * <code>obj</code> is an instance of <code>Folder</code>, delegates to
     * <code>equals(Folder)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param      obj The object to compare to.
     * @return Whether equal to the object specified.
     */
    public function equals($obj)
    {
        $thisclazz = get_class($this);
        if (!is_object($obj) || !($obj instanceof $thisclazz)) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey()
            || null === $obj->getPrimaryKey())  {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        if (null !== $this->getPrimaryKey()) {
            return crc32(serialize($this->getPrimaryKey()));
        }

        return crc32(serialize(clone $this));
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @param string $name The virtual column name
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return isset($this->virtualColumns[$name]);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @return mixed
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return Folder The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     *
     * @return Folder The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return   int
     */
    public function getId()
    {

        return $this->id;
    }

    /**
     * Get the [parent] column value.
     *
     * @return   int
     */
    public function getParent()
    {

        return $this->parent;
    }

    /**
     * Get the [link] column value.
     *
     * @return   string
     */
    public function getLink()
    {

        return $this->link;
    }

    /**
     * Get the [visible] column value.
     *
     * @return   int
     */
    public function getVisible()
    {

        return $this->visible;
    }

    /**
     * Get the [position] column value.
     *
     * @return   int
     */
    public function getPosition()
    {

        return $this->position;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->created_at;
        } else {
            return $this->created_at !== null ? $this->created_at->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->updated_at;
        } else {
            return $this->updated_at !== null ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Get the [version] column value.
     *
     * @return   int
     */
    public function getVersion()
    {

        return $this->version;
    }

    /**
     * Get the [optionally formatted] temporal [version_created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getVersionCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->version_created_at;
        } else {
            return $this->version_created_at !== null ? $this->version_created_at->format($format) : null;
        }
    }

    /**
     * Get the [version_created_by] column value.
     *
     * @return   string
     */
    public function getVersionCreatedBy()
    {

        return $this->version_created_by;
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = FolderTableMap::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [parent] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setParent($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->parent !== $v) {
            $this->parent = $v;
            $this->modifiedColumns[] = FolderTableMap::PARENT;
        }


        return $this;
    } // setParent()

    /**
     * Set the value of [link] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setLink($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->link !== $v) {
            $this->link = $v;
            $this->modifiedColumns[] = FolderTableMap::LINK;
        }


        return $this;
    } // setLink()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[] = FolderTableMap::VISIBLE;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = FolderTableMap::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[] = FolderTableMap::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[] = FolderTableMap::UPDATED_AT;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[] = FolderTableMap::VERSION;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[] = FolderTableMap::VERSION_CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[] = FolderTableMap::VERSION_CREATED_BY;
        }


        return $this;
    } // setVersionCreatedBy()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->version !== 0) {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : FolderTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : FolderTableMap::translateFieldName('Parent', TableMap::TYPE_PHPNAME, $indexType)];
            $this->parent = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : FolderTableMap::translateFieldName('Link', TableMap::TYPE_PHPNAME, $indexType)];
            $this->link = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : FolderTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : FolderTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : FolderTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : FolderTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : FolderTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : FolderTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : FolderTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 10; // 10 = FolderTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Folder object", 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(FolderTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildFolderQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collImages = null;

            $this->collDocuments = null;

            $this->collRewritings = null;

            $this->collContentFolders = null;

            $this->collFolderI18ns = null;

            $this->collFolderVersions = null;

            $this->collContents = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Folder::setDeleted()
     * @see Folder::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(FolderTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildFolderQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $con->commit();
                $this->setDeleted(true);
            } else {
                $con->commit();
            }
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(FolderTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(FolderTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(FolderTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(FolderTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(FolderTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                // versionable behavior
                if (isset($createVersion)) {
                    $this->addVersion($con);
                }
                FolderTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }
            $con->commit();

            return $affectedRows;
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                } else {
                    $this->doUpdate($con);
                }
                $affectedRows += 1;
                $this->resetModified();
            }

            if ($this->contentsScheduledForDeletion !== null) {
                if (!$this->contentsScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->contentsScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    ContentFolderQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->contentsScheduledForDeletion = null;
                }

                foreach ($this->getContents() as $content) {
                    if ($content->isModified()) {
                        $content->save($con);
                    }
                }
            } elseif ($this->collContents) {
                foreach ($this->collContents as $content) {
                    if ($content->isModified()) {
                        $content->save($con);
                    }
                }
            }

            if ($this->imagesScheduledForDeletion !== null) {
                if (!$this->imagesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ImageQuery::create()
                        ->filterByPrimaryKeys($this->imagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->imagesScheduledForDeletion = null;
                }
            }

                if ($this->collImages !== null) {
            foreach ($this->collImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->documentsScheduledForDeletion !== null) {
                if (!$this->documentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\DocumentQuery::create()
                        ->filterByPrimaryKeys($this->documentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->documentsScheduledForDeletion = null;
                }
            }

                if ($this->collDocuments !== null) {
            foreach ($this->collDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->rewritingsScheduledForDeletion !== null) {
                if (!$this->rewritingsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\RewritingQuery::create()
                        ->filterByPrimaryKeys($this->rewritingsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->rewritingsScheduledForDeletion = null;
                }
            }

                if ($this->collRewritings !== null) {
            foreach ($this->collRewritings as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentFoldersScheduledForDeletion !== null) {
                if (!$this->contentFoldersScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentFolderQuery::create()
                        ->filterByPrimaryKeys($this->contentFoldersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentFoldersScheduledForDeletion = null;
                }
            }

                if ($this->collContentFolders !== null) {
            foreach ($this->collContentFolders as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->folderI18nsScheduledForDeletion !== null) {
                if (!$this->folderI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FolderI18nQuery::create()
                        ->filterByPrimaryKeys($this->folderI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->folderI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collFolderI18ns !== null) {
            foreach ($this->collFolderI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->folderVersionsScheduledForDeletion !== null) {
                if (!$this->folderVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FolderVersionQuery::create()
                        ->filterByPrimaryKeys($this->folderVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->folderVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collFolderVersions !== null) {
            foreach ($this->collFolderVersions as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[] = FolderTableMap::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . FolderTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(FolderTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(FolderTableMap::PARENT)) {
            $modifiedColumns[':p' . $index++]  = 'PARENT';
        }
        if ($this->isColumnModified(FolderTableMap::LINK)) {
            $modifiedColumns[':p' . $index++]  = 'LINK';
        }
        if ($this->isColumnModified(FolderTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = 'VISIBLE';
        }
        if ($this->isColumnModified(FolderTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = 'POSITION';
        }
        if ($this->isColumnModified(FolderTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(FolderTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }
        if ($this->isColumnModified(FolderTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION';
        }
        if ($this->isColumnModified(FolderTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION_CREATED_AT';
        }
        if ($this->isColumnModified(FolderTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION_CREATED_BY';
        }

        $sql = sprintf(
            'INSERT INTO folder (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'PARENT':
                        $stmt->bindValue($identifier, $this->parent, PDO::PARAM_INT);
                        break;
                    case 'LINK':
                        $stmt->bindValue($identifier, $this->link, PDO::PARAM_STR);
                        break;
                    case 'VISIBLE':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case 'POSITION':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'VERSION':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_INT);
                        break;
                    case 'VERSION_CREATED_AT':
                        $stmt->bindValue($identifier, $this->version_created_at ? $this->version_created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'VERSION_CREATED_BY':
                        $stmt->bindValue($identifier, $this->version_created_by, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = FolderTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getParent();
                break;
            case 2:
                return $this->getLink();
                break;
            case 3:
                return $this->getVisible();
                break;
            case 4:
                return $this->getPosition();
                break;
            case 5:
                return $this->getCreatedAt();
                break;
            case 6:
                return $this->getUpdatedAt();
                break;
            case 7:
                return $this->getVersion();
                break;
            case 8:
                return $this->getVersionCreatedAt();
                break;
            case 9:
                return $this->getVersionCreatedBy();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['Folder'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Folder'][$this->getPrimaryKey()] = true;
        $keys = FolderTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getParent(),
            $keys[2] => $this->getLink(),
            $keys[3] => $this->getVisible(),
            $keys[4] => $this->getPosition(),
            $keys[5] => $this->getCreatedAt(),
            $keys[6] => $this->getUpdatedAt(),
            $keys[7] => $this->getVersion(),
            $keys[8] => $this->getVersionCreatedAt(),
            $keys[9] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach($virtualColumns as $key => $virtualColumn)
        {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collImages) {
                $result['Images'] = $this->collImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collDocuments) {
                $result['Documents'] = $this->collDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collRewritings) {
                $result['Rewritings'] = $this->collRewritings->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentFolders) {
                $result['ContentFolders'] = $this->collContentFolders->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFolderI18ns) {
                $result['FolderI18ns'] = $this->collFolderI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFolderVersions) {
                $result['FolderVersions'] = $this->collFolderVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param      string $name
     * @param      mixed  $value field value
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return void
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = FolderTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @param      mixed $value field value
     * @return void
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setParent($value);
                break;
            case 2:
                $this->setLink($value);
                break;
            case 3:
                $this->setVisible($value);
                break;
            case 4:
                $this->setPosition($value);
                break;
            case 5:
                $this->setCreatedAt($value);
                break;
            case 6:
                $this->setUpdatedAt($value);
                break;
            case 7:
                $this->setVersion($value);
                break;
            case 8:
                $this->setVersionCreatedAt($value);
                break;
            case 9:
                $this->setVersionCreatedBy($value);
                break;
        } // switch()
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = FolderTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setParent($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setLink($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setVisible($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPosition($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setCreatedAt($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setUpdatedAt($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setVersion($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setVersionCreatedAt($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setVersionCreatedBy($arr[$keys[9]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(FolderTableMap::DATABASE_NAME);

        if ($this->isColumnModified(FolderTableMap::ID)) $criteria->add(FolderTableMap::ID, $this->id);
        if ($this->isColumnModified(FolderTableMap::PARENT)) $criteria->add(FolderTableMap::PARENT, $this->parent);
        if ($this->isColumnModified(FolderTableMap::LINK)) $criteria->add(FolderTableMap::LINK, $this->link);
        if ($this->isColumnModified(FolderTableMap::VISIBLE)) $criteria->add(FolderTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(FolderTableMap::POSITION)) $criteria->add(FolderTableMap::POSITION, $this->position);
        if ($this->isColumnModified(FolderTableMap::CREATED_AT)) $criteria->add(FolderTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(FolderTableMap::UPDATED_AT)) $criteria->add(FolderTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(FolderTableMap::VERSION)) $criteria->add(FolderTableMap::VERSION, $this->version);
        if ($this->isColumnModified(FolderTableMap::VERSION_CREATED_AT)) $criteria->add(FolderTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(FolderTableMap::VERSION_CREATED_BY)) $criteria->add(FolderTableMap::VERSION_CREATED_BY, $this->version_created_by);

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(FolderTableMap::DATABASE_NAME);
        $criteria->add(FolderTableMap::ID, $this->id);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return   int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Thelia\Model\Folder (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setParent($this->getParent());
        $copyObj->setLink($this->getLink());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getRewritings() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addRewriting($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentFolders() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentFolder($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFolderI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFolderI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFolderVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFolderVersion($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return                 \Thelia\Model\Folder Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('Image' == $relationName) {
            return $this->initImages();
        }
        if ('Document' == $relationName) {
            return $this->initDocuments();
        }
        if ('Rewriting' == $relationName) {
            return $this->initRewritings();
        }
        if ('ContentFolder' == $relationName) {
            return $this->initContentFolders();
        }
        if ('FolderI18n' == $relationName) {
            return $this->initFolderI18ns();
        }
        if ('FolderVersion' == $relationName) {
            return $this->initFolderVersions();
        }
    }

    /**
     * Clears out the collImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addImages()
     */
    public function clearImages()
    {
        $this->collImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collImages collection loaded partially.
     */
    public function resetPartialImages($v = true)
    {
        $this->collImagesPartial = $v;
    }

    /**
     * Initializes the collImages collection.
     *
     * By default this just sets the collImages collection to an empty array (like clearcollImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initImages($overrideExisting = true)
    {
        if (null !== $this->collImages && !$overrideExisting) {
            return;
        }
        $this->collImages = new ObjectCollection();
        $this->collImages->setModel('\Thelia\Model\Image');
    }

    /**
     * Gets an array of ChildImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildImage[] List of ChildImage objects
     * @throws PropelException
     */
    public function getImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                // return empty collection
                $this->initImages();
            } else {
                $collImages = ChildImageQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collImagesPartial && count($collImages)) {
                        $this->initImages(false);

                        foreach ($collImages as $obj) {
                            if (false == $this->collImages->contains($obj)) {
                                $this->collImages->append($obj);
                            }
                        }

                        $this->collImagesPartial = true;
                    }

                    $collImages->getInternalIterator()->rewind();

                    return $collImages;
                }

                if ($partial && $this->collImages) {
                    foreach ($this->collImages as $obj) {
                        if ($obj->isNew()) {
                            $collImages[] = $obj;
                        }
                    }
                }

                $this->collImages = $collImages;
                $this->collImagesPartial = false;
            }
        }

        return $this->collImages;
    }

    /**
     * Sets a collection of Image objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $images A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setImages(Collection $images, ConnectionInterface $con = null)
    {
        $imagesToDelete = $this->getImages(new Criteria(), $con)->diff($images);


        $this->imagesScheduledForDeletion = $imagesToDelete;

        foreach ($imagesToDelete as $imageRemoved) {
            $imageRemoved->setFolder(null);
        }

        $this->collImages = null;
        foreach ($images as $image) {
            $this->addImage($image);
        }

        $this->collImages = $images;
        $this->collImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Image objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Image objects.
     * @throws PropelException
     */
    public function countImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getImages());
            }

            $query = ChildImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collImages);
    }

    /**
     * Method called to associate a ChildImage object to this object
     * through the ChildImage foreign key attribute.
     *
     * @param    ChildImage $l ChildImage
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addImage(ChildImage $l)
    {
        if ($this->collImages === null) {
            $this->initImages();
            $this->collImagesPartial = true;
        }

        if (!in_array($l, $this->collImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddImage($l);
        }

        return $this;
    }

    /**
     * @param Image $image The image object to add.
     */
    protected function doAddImage($image)
    {
        $this->collImages[]= $image;
        $image->setFolder($this);
    }

    /**
     * @param  Image $image The image object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeImage($image)
    {
        if ($this->getImages()->contains($image)) {
            $this->collImages->remove($this->collImages->search($image));
            if (null === $this->imagesScheduledForDeletion) {
                $this->imagesScheduledForDeletion = clone $this->collImages;
                $this->imagesScheduledForDeletion->clear();
            }
            $this->imagesScheduledForDeletion[]= $image;
            $image->setFolder(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildImage[] List of ChildImage objects
     */
    public function getImagesJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildImageQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildImage[] List of ChildImage objects
     */
    public function getImagesJoinCategory($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildImageQuery::create(null, $criteria);
        $query->joinWith('Category', $joinBehavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildImage[] List of ChildImage objects
     */
    public function getImagesJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildImageQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getImages($query, $con);
    }

    /**
     * Clears out the collDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDocuments()
     */
    public function clearDocuments()
    {
        $this->collDocuments = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collDocuments collection loaded partially.
     */
    public function resetPartialDocuments($v = true)
    {
        $this->collDocumentsPartial = $v;
    }

    /**
     * Initializes the collDocuments collection.
     *
     * By default this just sets the collDocuments collection to an empty array (like clearcollDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDocuments($overrideExisting = true)
    {
        if (null !== $this->collDocuments && !$overrideExisting) {
            return;
        }
        $this->collDocuments = new ObjectCollection();
        $this->collDocuments->setModel('\Thelia\Model\Document');
    }

    /**
     * Gets an array of ChildDocument objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildDocument[] List of ChildDocument objects
     * @throws PropelException
     */
    public function getDocuments($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                // return empty collection
                $this->initDocuments();
            } else {
                $collDocuments = ChildDocumentQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collDocumentsPartial && count($collDocuments)) {
                        $this->initDocuments(false);

                        foreach ($collDocuments as $obj) {
                            if (false == $this->collDocuments->contains($obj)) {
                                $this->collDocuments->append($obj);
                            }
                        }

                        $this->collDocumentsPartial = true;
                    }

                    $collDocuments->getInternalIterator()->rewind();

                    return $collDocuments;
                }

                if ($partial && $this->collDocuments) {
                    foreach ($this->collDocuments as $obj) {
                        if ($obj->isNew()) {
                            $collDocuments[] = $obj;
                        }
                    }
                }

                $this->collDocuments = $collDocuments;
                $this->collDocumentsPartial = false;
            }
        }

        return $this->collDocuments;
    }

    /**
     * Sets a collection of Document objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $documents A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setDocuments(Collection $documents, ConnectionInterface $con = null)
    {
        $documentsToDelete = $this->getDocuments(new Criteria(), $con)->diff($documents);


        $this->documentsScheduledForDeletion = $documentsToDelete;

        foreach ($documentsToDelete as $documentRemoved) {
            $documentRemoved->setFolder(null);
        }

        $this->collDocuments = null;
        foreach ($documents as $document) {
            $this->addDocument($document);
        }

        $this->collDocuments = $documents;
        $this->collDocumentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Document objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Document objects.
     * @throws PropelException
     */
    public function countDocuments(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getDocuments());
            }

            $query = ChildDocumentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collDocuments);
    }

    /**
     * Method called to associate a ChildDocument object to this object
     * through the ChildDocument foreign key attribute.
     *
     * @param    ChildDocument $l ChildDocument
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addDocument(ChildDocument $l)
    {
        if ($this->collDocuments === null) {
            $this->initDocuments();
            $this->collDocumentsPartial = true;
        }

        if (!in_array($l, $this->collDocuments->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddDocument($l);
        }

        return $this;
    }

    /**
     * @param Document $document The document object to add.
     */
    protected function doAddDocument($document)
    {
        $this->collDocuments[]= $document;
        $document->setFolder($this);
    }

    /**
     * @param  Document $document The document object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeDocument($document)
    {
        if ($this->getDocuments()->contains($document)) {
            $this->collDocuments->remove($this->collDocuments->search($document));
            if (null === $this->documentsScheduledForDeletion) {
                $this->documentsScheduledForDeletion = clone $this->collDocuments;
                $this->documentsScheduledForDeletion->clear();
            }
            $this->documentsScheduledForDeletion[]= $document;
            $document->setFolder(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildDocument[] List of ChildDocument objects
     */
    public function getDocumentsJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildDocumentQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildDocument[] List of ChildDocument objects
     */
    public function getDocumentsJoinCategory($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildDocumentQuery::create(null, $criteria);
        $query->joinWith('Category', $joinBehavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildDocument[] List of ChildDocument objects
     */
    public function getDocumentsJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildDocumentQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getDocuments($query, $con);
    }

    /**
     * Clears out the collRewritings collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addRewritings()
     */
    public function clearRewritings()
    {
        $this->collRewritings = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collRewritings collection loaded partially.
     */
    public function resetPartialRewritings($v = true)
    {
        $this->collRewritingsPartial = $v;
    }

    /**
     * Initializes the collRewritings collection.
     *
     * By default this just sets the collRewritings collection to an empty array (like clearcollRewritings());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initRewritings($overrideExisting = true)
    {
        if (null !== $this->collRewritings && !$overrideExisting) {
            return;
        }
        $this->collRewritings = new ObjectCollection();
        $this->collRewritings->setModel('\Thelia\Model\Rewriting');
    }

    /**
     * Gets an array of ChildRewriting objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildRewriting[] List of ChildRewriting objects
     * @throws PropelException
     */
    public function getRewritings($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                // return empty collection
                $this->initRewritings();
            } else {
                $collRewritings = ChildRewritingQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collRewritingsPartial && count($collRewritings)) {
                        $this->initRewritings(false);

                        foreach ($collRewritings as $obj) {
                            if (false == $this->collRewritings->contains($obj)) {
                                $this->collRewritings->append($obj);
                            }
                        }

                        $this->collRewritingsPartial = true;
                    }

                    $collRewritings->getInternalIterator()->rewind();

                    return $collRewritings;
                }

                if ($partial && $this->collRewritings) {
                    foreach ($this->collRewritings as $obj) {
                        if ($obj->isNew()) {
                            $collRewritings[] = $obj;
                        }
                    }
                }

                $this->collRewritings = $collRewritings;
                $this->collRewritingsPartial = false;
            }
        }

        return $this->collRewritings;
    }

    /**
     * Sets a collection of Rewriting objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $rewritings A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setRewritings(Collection $rewritings, ConnectionInterface $con = null)
    {
        $rewritingsToDelete = $this->getRewritings(new Criteria(), $con)->diff($rewritings);


        $this->rewritingsScheduledForDeletion = $rewritingsToDelete;

        foreach ($rewritingsToDelete as $rewritingRemoved) {
            $rewritingRemoved->setFolder(null);
        }

        $this->collRewritings = null;
        foreach ($rewritings as $rewriting) {
            $this->addRewriting($rewriting);
        }

        $this->collRewritings = $rewritings;
        $this->collRewritingsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Rewriting objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Rewriting objects.
     * @throws PropelException
     */
    public function countRewritings(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getRewritings());
            }

            $query = ChildRewritingQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collRewritings);
    }

    /**
     * Method called to associate a ChildRewriting object to this object
     * through the ChildRewriting foreign key attribute.
     *
     * @param    ChildRewriting $l ChildRewriting
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addRewriting(ChildRewriting $l)
    {
        if ($this->collRewritings === null) {
            $this->initRewritings();
            $this->collRewritingsPartial = true;
        }

        if (!in_array($l, $this->collRewritings->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddRewriting($l);
        }

        return $this;
    }

    /**
     * @param Rewriting $rewriting The rewriting object to add.
     */
    protected function doAddRewriting($rewriting)
    {
        $this->collRewritings[]= $rewriting;
        $rewriting->setFolder($this);
    }

    /**
     * @param  Rewriting $rewriting The rewriting object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeRewriting($rewriting)
    {
        if ($this->getRewritings()->contains($rewriting)) {
            $this->collRewritings->remove($this->collRewritings->search($rewriting));
            if (null === $this->rewritingsScheduledForDeletion) {
                $this->rewritingsScheduledForDeletion = clone $this->collRewritings;
                $this->rewritingsScheduledForDeletion->clear();
            }
            $this->rewritingsScheduledForDeletion[]= $rewriting;
            $rewriting->setFolder(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildRewriting[] List of ChildRewriting objects
     */
    public function getRewritingsJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildRewritingQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildRewriting[] List of ChildRewriting objects
     */
    public function getRewritingsJoinCategory($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildRewritingQuery::create(null, $criteria);
        $query->joinWith('Category', $joinBehavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildRewriting[] List of ChildRewriting objects
     */
    public function getRewritingsJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildRewritingQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getRewritings($query, $con);
    }

    /**
     * Clears out the collContentFolders collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentFolders()
     */
    public function clearContentFolders()
    {
        $this->collContentFolders = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentFolders collection loaded partially.
     */
    public function resetPartialContentFolders($v = true)
    {
        $this->collContentFoldersPartial = $v;
    }

    /**
     * Initializes the collContentFolders collection.
     *
     * By default this just sets the collContentFolders collection to an empty array (like clearcollContentFolders());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentFolders($overrideExisting = true)
    {
        if (null !== $this->collContentFolders && !$overrideExisting) {
            return;
        }
        $this->collContentFolders = new ObjectCollection();
        $this->collContentFolders->setModel('\Thelia\Model\ContentFolder');
    }

    /**
     * Gets an array of ChildContentFolder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentFolder[] List of ChildContentFolder objects
     * @throws PropelException
     */
    public function getContentFolders($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentFoldersPartial && !$this->isNew();
        if (null === $this->collContentFolders || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentFolders) {
                // return empty collection
                $this->initContentFolders();
            } else {
                $collContentFolders = ChildContentFolderQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentFoldersPartial && count($collContentFolders)) {
                        $this->initContentFolders(false);

                        foreach ($collContentFolders as $obj) {
                            if (false == $this->collContentFolders->contains($obj)) {
                                $this->collContentFolders->append($obj);
                            }
                        }

                        $this->collContentFoldersPartial = true;
                    }

                    $collContentFolders->getInternalIterator()->rewind();

                    return $collContentFolders;
                }

                if ($partial && $this->collContentFolders) {
                    foreach ($this->collContentFolders as $obj) {
                        if ($obj->isNew()) {
                            $collContentFolders[] = $obj;
                        }
                    }
                }

                $this->collContentFolders = $collContentFolders;
                $this->collContentFoldersPartial = false;
            }
        }

        return $this->collContentFolders;
    }

    /**
     * Sets a collection of ContentFolder objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentFolders A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setContentFolders(Collection $contentFolders, ConnectionInterface $con = null)
    {
        $contentFoldersToDelete = $this->getContentFolders(new Criteria(), $con)->diff($contentFolders);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->contentFoldersScheduledForDeletion = clone $contentFoldersToDelete;

        foreach ($contentFoldersToDelete as $contentFolderRemoved) {
            $contentFolderRemoved->setFolder(null);
        }

        $this->collContentFolders = null;
        foreach ($contentFolders as $contentFolder) {
            $this->addContentFolder($contentFolder);
        }

        $this->collContentFolders = $contentFolders;
        $this->collContentFoldersPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentFolder objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentFolder objects.
     * @throws PropelException
     */
    public function countContentFolders(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentFoldersPartial && !$this->isNew();
        if (null === $this->collContentFolders || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentFolders) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentFolders());
            }

            $query = ChildContentFolderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collContentFolders);
    }

    /**
     * Method called to associate a ChildContentFolder object to this object
     * through the ChildContentFolder foreign key attribute.
     *
     * @param    ChildContentFolder $l ChildContentFolder
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addContentFolder(ChildContentFolder $l)
    {
        if ($this->collContentFolders === null) {
            $this->initContentFolders();
            $this->collContentFoldersPartial = true;
        }

        if (!in_array($l, $this->collContentFolders->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentFolder($l);
        }

        return $this;
    }

    /**
     * @param ContentFolder $contentFolder The contentFolder object to add.
     */
    protected function doAddContentFolder($contentFolder)
    {
        $this->collContentFolders[]= $contentFolder;
        $contentFolder->setFolder($this);
    }

    /**
     * @param  ContentFolder $contentFolder The contentFolder object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeContentFolder($contentFolder)
    {
        if ($this->getContentFolders()->contains($contentFolder)) {
            $this->collContentFolders->remove($this->collContentFolders->search($contentFolder));
            if (null === $this->contentFoldersScheduledForDeletion) {
                $this->contentFoldersScheduledForDeletion = clone $this->collContentFolders;
                $this->contentFoldersScheduledForDeletion->clear();
            }
            $this->contentFoldersScheduledForDeletion[]= clone $contentFolder;
            $contentFolder->setFolder(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Folder is new, it will return
     * an empty collection; or if this Folder has previously
     * been saved, it will retrieve related ContentFolders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Folder.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildContentFolder[] List of ChildContentFolder objects
     */
    public function getContentFoldersJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildContentFolderQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getContentFolders($query, $con);
    }

    /**
     * Clears out the collFolderI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFolderI18ns()
     */
    public function clearFolderI18ns()
    {
        $this->collFolderI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFolderI18ns collection loaded partially.
     */
    public function resetPartialFolderI18ns($v = true)
    {
        $this->collFolderI18nsPartial = $v;
    }

    /**
     * Initializes the collFolderI18ns collection.
     *
     * By default this just sets the collFolderI18ns collection to an empty array (like clearcollFolderI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFolderI18ns($overrideExisting = true)
    {
        if (null !== $this->collFolderI18ns && !$overrideExisting) {
            return;
        }
        $this->collFolderI18ns = new ObjectCollection();
        $this->collFolderI18ns->setModel('\Thelia\Model\FolderI18n');
    }

    /**
     * Gets an array of ChildFolderI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFolderI18n[] List of ChildFolderI18n objects
     * @throws PropelException
     */
    public function getFolderI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFolderI18nsPartial && !$this->isNew();
        if (null === $this->collFolderI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFolderI18ns) {
                // return empty collection
                $this->initFolderI18ns();
            } else {
                $collFolderI18ns = ChildFolderI18nQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFolderI18nsPartial && count($collFolderI18ns)) {
                        $this->initFolderI18ns(false);

                        foreach ($collFolderI18ns as $obj) {
                            if (false == $this->collFolderI18ns->contains($obj)) {
                                $this->collFolderI18ns->append($obj);
                            }
                        }

                        $this->collFolderI18nsPartial = true;
                    }

                    $collFolderI18ns->getInternalIterator()->rewind();

                    return $collFolderI18ns;
                }

                if ($partial && $this->collFolderI18ns) {
                    foreach ($this->collFolderI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collFolderI18ns[] = $obj;
                        }
                    }
                }

                $this->collFolderI18ns = $collFolderI18ns;
                $this->collFolderI18nsPartial = false;
            }
        }

        return $this->collFolderI18ns;
    }

    /**
     * Sets a collection of FolderI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $folderI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setFolderI18ns(Collection $folderI18ns, ConnectionInterface $con = null)
    {
        $folderI18nsToDelete = $this->getFolderI18ns(new Criteria(), $con)->diff($folderI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->folderI18nsScheduledForDeletion = clone $folderI18nsToDelete;

        foreach ($folderI18nsToDelete as $folderI18nRemoved) {
            $folderI18nRemoved->setFolder(null);
        }

        $this->collFolderI18ns = null;
        foreach ($folderI18ns as $folderI18n) {
            $this->addFolderI18n($folderI18n);
        }

        $this->collFolderI18ns = $folderI18ns;
        $this->collFolderI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FolderI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FolderI18n objects.
     * @throws PropelException
     */
    public function countFolderI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFolderI18nsPartial && !$this->isNew();
        if (null === $this->collFolderI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFolderI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFolderI18ns());
            }

            $query = ChildFolderI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collFolderI18ns);
    }

    /**
     * Method called to associate a ChildFolderI18n object to this object
     * through the ChildFolderI18n foreign key attribute.
     *
     * @param    ChildFolderI18n $l ChildFolderI18n
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addFolderI18n(ChildFolderI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collFolderI18ns === null) {
            $this->initFolderI18ns();
            $this->collFolderI18nsPartial = true;
        }

        if (!in_array($l, $this->collFolderI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFolderI18n($l);
        }

        return $this;
    }

    /**
     * @param FolderI18n $folderI18n The folderI18n object to add.
     */
    protected function doAddFolderI18n($folderI18n)
    {
        $this->collFolderI18ns[]= $folderI18n;
        $folderI18n->setFolder($this);
    }

    /**
     * @param  FolderI18n $folderI18n The folderI18n object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeFolderI18n($folderI18n)
    {
        if ($this->getFolderI18ns()->contains($folderI18n)) {
            $this->collFolderI18ns->remove($this->collFolderI18ns->search($folderI18n));
            if (null === $this->folderI18nsScheduledForDeletion) {
                $this->folderI18nsScheduledForDeletion = clone $this->collFolderI18ns;
                $this->folderI18nsScheduledForDeletion->clear();
            }
            $this->folderI18nsScheduledForDeletion[]= clone $folderI18n;
            $folderI18n->setFolder(null);
        }

        return $this;
    }

    /**
     * Clears out the collFolderVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFolderVersions()
     */
    public function clearFolderVersions()
    {
        $this->collFolderVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFolderVersions collection loaded partially.
     */
    public function resetPartialFolderVersions($v = true)
    {
        $this->collFolderVersionsPartial = $v;
    }

    /**
     * Initializes the collFolderVersions collection.
     *
     * By default this just sets the collFolderVersions collection to an empty array (like clearcollFolderVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFolderVersions($overrideExisting = true)
    {
        if (null !== $this->collFolderVersions && !$overrideExisting) {
            return;
        }
        $this->collFolderVersions = new ObjectCollection();
        $this->collFolderVersions->setModel('\Thelia\Model\FolderVersion');
    }

    /**
     * Gets an array of ChildFolderVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFolderVersion[] List of ChildFolderVersion objects
     * @throws PropelException
     */
    public function getFolderVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFolderVersionsPartial && !$this->isNew();
        if (null === $this->collFolderVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFolderVersions) {
                // return empty collection
                $this->initFolderVersions();
            } else {
                $collFolderVersions = ChildFolderVersionQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFolderVersionsPartial && count($collFolderVersions)) {
                        $this->initFolderVersions(false);

                        foreach ($collFolderVersions as $obj) {
                            if (false == $this->collFolderVersions->contains($obj)) {
                                $this->collFolderVersions->append($obj);
                            }
                        }

                        $this->collFolderVersionsPartial = true;
                    }

                    $collFolderVersions->getInternalIterator()->rewind();

                    return $collFolderVersions;
                }

                if ($partial && $this->collFolderVersions) {
                    foreach ($this->collFolderVersions as $obj) {
                        if ($obj->isNew()) {
                            $collFolderVersions[] = $obj;
                        }
                    }
                }

                $this->collFolderVersions = $collFolderVersions;
                $this->collFolderVersionsPartial = false;
            }
        }

        return $this->collFolderVersions;
    }

    /**
     * Sets a collection of FolderVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $folderVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFolder The current object (for fluent API support)
     */
    public function setFolderVersions(Collection $folderVersions, ConnectionInterface $con = null)
    {
        $folderVersionsToDelete = $this->getFolderVersions(new Criteria(), $con)->diff($folderVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->folderVersionsScheduledForDeletion = clone $folderVersionsToDelete;

        foreach ($folderVersionsToDelete as $folderVersionRemoved) {
            $folderVersionRemoved->setFolder(null);
        }

        $this->collFolderVersions = null;
        foreach ($folderVersions as $folderVersion) {
            $this->addFolderVersion($folderVersion);
        }

        $this->collFolderVersions = $folderVersions;
        $this->collFolderVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FolderVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FolderVersion objects.
     * @throws PropelException
     */
    public function countFolderVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFolderVersionsPartial && !$this->isNew();
        if (null === $this->collFolderVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFolderVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFolderVersions());
            }

            $query = ChildFolderVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFolder($this)
                ->count($con);
        }

        return count($this->collFolderVersions);
    }

    /**
     * Method called to associate a ChildFolderVersion object to this object
     * through the ChildFolderVersion foreign key attribute.
     *
     * @param    ChildFolderVersion $l ChildFolderVersion
     * @return   \Thelia\Model\Folder The current object (for fluent API support)
     */
    public function addFolderVersion(ChildFolderVersion $l)
    {
        if ($this->collFolderVersions === null) {
            $this->initFolderVersions();
            $this->collFolderVersionsPartial = true;
        }

        if (!in_array($l, $this->collFolderVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFolderVersion($l);
        }

        return $this;
    }

    /**
     * @param FolderVersion $folderVersion The folderVersion object to add.
     */
    protected function doAddFolderVersion($folderVersion)
    {
        $this->collFolderVersions[]= $folderVersion;
        $folderVersion->setFolder($this);
    }

    /**
     * @param  FolderVersion $folderVersion The folderVersion object to remove.
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeFolderVersion($folderVersion)
    {
        if ($this->getFolderVersions()->contains($folderVersion)) {
            $this->collFolderVersions->remove($this->collFolderVersions->search($folderVersion));
            if (null === $this->folderVersionsScheduledForDeletion) {
                $this->folderVersionsScheduledForDeletion = clone $this->collFolderVersions;
                $this->folderVersionsScheduledForDeletion->clear();
            }
            $this->folderVersionsScheduledForDeletion[]= clone $folderVersion;
            $folderVersion->setFolder(null);
        }

        return $this;
    }

    /**
     * Clears out the collContents collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContents()
     */
    public function clearContents()
    {
        $this->collContents = null; // important to set this to NULL since that means it is uninitialized
        $this->collContentsPartial = null;
    }

    /**
     * Initializes the collContents collection.
     *
     * By default this just sets the collContents collection to an empty collection (like clearContents());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initContents()
    {
        $this->collContents = new ObjectCollection();
        $this->collContents->setModel('\Thelia\Model\Content');
    }

    /**
     * Gets a collection of ChildContent objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFolder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildContent[] List of ChildContent objects
     */
    public function getContents($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collContents || null !== $criteria) {
            if ($this->isNew() && null === $this->collContents) {
                // return empty collection
                $this->initContents();
            } else {
                $collContents = ChildContentQuery::create(null, $criteria)
                    ->filterByFolder($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collContents;
                }
                $this->collContents = $collContents;
            }
        }

        return $this->collContents;
    }

    /**
     * Sets a collection of Content objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $contents A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildFolder The current object (for fluent API support)
     */
    public function setContents(Collection $contents, ConnectionInterface $con = null)
    {
        $this->clearContents();
        $currentContents = $this->getContents();

        $this->contentsScheduledForDeletion = $currentContents->diff($contents);

        foreach ($contents as $content) {
            if (!$currentContents->contains($content)) {
                $this->doAddContent($content);
            }
        }

        $this->collContents = $contents;

        return $this;
    }

    /**
     * Gets the number of ChildContent objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildContent objects
     */
    public function countContents($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collContents || null !== $criteria) {
            if ($this->isNew() && null === $this->collContents) {
                return 0;
            } else {
                $query = ChildContentQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByFolder($this)
                    ->count($con);
            }
        } else {
            return count($this->collContents);
        }
    }

    /**
     * Associate a ChildContent object to this object
     * through the content_folder cross reference table.
     *
     * @param  ChildContent $content The ChildContentFolder object to relate
     * @return ChildFolder The current object (for fluent API support)
     */
    public function addContent(ChildContent $content)
    {
        if ($this->collContents === null) {
            $this->initContents();
        }

        if (!$this->collContents->contains($content)) { // only add it if the **same** object is not already associated
            $this->doAddContent($content);
            $this->collContents[] = $content;
        }

        return $this;
    }

    /**
     * @param    Content $content The content object to add.
     */
    protected function doAddContent($content)
    {
        $contentFolder = new ChildContentFolder();
        $contentFolder->setContent($content);
        $this->addContentFolder($contentFolder);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$content->getFolders()->contains($this)) {
            $foreignCollection   = $content->getFolders();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildContent object to this object
     * through the content_folder cross reference table.
     *
     * @param ChildContent $content The ChildContentFolder object to relate
     * @return ChildFolder The current object (for fluent API support)
     */
    public function removeContent(ChildContent $content)
    {
        if ($this->getContents()->contains($content)) {
            $this->collContents->remove($this->collContents->search($content));

            if (null === $this->contentsScheduledForDeletion) {
                $this->contentsScheduledForDeletion = clone $this->collContents;
                $this->contentsScheduledForDeletion->clear();
            }

            $this->contentsScheduledForDeletion[] = $content;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->parent = null;
        $this->link = null;
        $this->visible = null;
        $this->position = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references to other model objects or collections of model objects.
     *
     * This method is a user-space workaround for PHP's inability to garbage collect
     * objects with circular references (even in PHP 5.3). This is currently necessary
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collImages) {
                foreach ($this->collImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collDocuments) {
                foreach ($this->collDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collRewritings) {
                foreach ($this->collRewritings as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentFolders) {
                foreach ($this->collContentFolders as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFolderI18ns) {
                foreach ($this->collFolderI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFolderVersions) {
                foreach ($this->collFolderVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContents) {
                foreach ($this->collContents as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_EN';
        $this->currentTranslations = null;

        if ($this->collImages instanceof Collection) {
            $this->collImages->clearIterator();
        }
        $this->collImages = null;
        if ($this->collDocuments instanceof Collection) {
            $this->collDocuments->clearIterator();
        }
        $this->collDocuments = null;
        if ($this->collRewritings instanceof Collection) {
            $this->collRewritings->clearIterator();
        }
        $this->collRewritings = null;
        if ($this->collContentFolders instanceof Collection) {
            $this->collContentFolders->clearIterator();
        }
        $this->collContentFolders = null;
        if ($this->collFolderI18ns instanceof Collection) {
            $this->collFolderI18ns->clearIterator();
        }
        $this->collFolderI18ns = null;
        if ($this->collFolderVersions instanceof Collection) {
            $this->collFolderVersions->clearIterator();
        }
        $this->collFolderVersions = null;
        if ($this->collContents instanceof Collection) {
            $this->collContents->clearIterator();
        }
        $this->collContents = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(FolderTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildFolder The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = FolderTableMap::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildFolder The current object (for fluent API support)
     */
    public function setLocale($locale = 'en_EN')
    {
        $this->currentLocale = $locale;

        return $this;
    }

    /**
     * Gets the locale for translations
     *
     * @return    string $locale Locale to use for the translation, e.g. 'fr_FR'
     */
    public function getLocale()
    {
        return $this->currentLocale;
    }

    /**
     * Returns the current translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildFolderI18n */
    public function getTranslation($locale = 'en_EN', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collFolderI18ns) {
                foreach ($this->collFolderI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildFolderI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildFolderI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addFolderI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildFolder The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_EN', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildFolderI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collFolderI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collFolderI18ns[$key]);
                break;
            }
        }

        return $this;
    }

    /**
     * Returns the current translation
     *
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildFolderI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [title] column value.
         *
         * @return   string
         */
        public function getTitle()
        {
        return $this->getCurrentTranslation()->getTitle();
    }


        /**
         * Set the value of [title] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\FolderI18n The current object (for fluent API support)
         */
        public function setTitle($v)
        {    $this->getCurrentTranslation()->setTitle($v);

        return $this;
    }


        /**
         * Get the [description] column value.
         *
         * @return   string
         */
        public function getDescription()
        {
        return $this->getCurrentTranslation()->getDescription();
    }


        /**
         * Set the value of [description] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\FolderI18n The current object (for fluent API support)
         */
        public function setDescription($v)
        {    $this->getCurrentTranslation()->setDescription($v);

        return $this;
    }


        /**
         * Get the [chapo] column value.
         *
         * @return   string
         */
        public function getChapo()
        {
        return $this->getCurrentTranslation()->getChapo();
    }


        /**
         * Set the value of [chapo] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\FolderI18n The current object (for fluent API support)
         */
        public function setChapo($v)
        {    $this->getCurrentTranslation()->setChapo($v);

        return $this;
    }


        /**
         * Get the [postscriptum] column value.
         *
         * @return   string
         */
        public function getPostscriptum()
        {
        return $this->getCurrentTranslation()->getPostscriptum();
    }


        /**
         * Set the value of [postscriptum] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\FolderI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Folder
     */
    public function enforceVersioning()
    {
        $this->enforceVersion = true;

        return $this;
    }

    /**
     * Checks whether the current state must be recorded as a version
     *
     * @return  boolean
     */
    public function isVersioningNecessary($con = null)
    {
        if ($this->alreadyInSave) {
            return false;
        }

        if ($this->enforceVersion) {
            return true;
        }

        if (ChildFolderQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildFolderVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildFolderVersion();
        $version->setId($this->getId());
        $version->setParent($this->getParent());
        $version->setLink($this->getLink());
        $version->setVisible($this->getVisible());
        $version->setPosition($this->getPosition());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setFolder($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildFolder The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildFolder object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildFolderVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildFolder The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildFolder'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setParent($version->getParent());
        $this->setLink($version->getLink());
        $this->setVisible($version->getVisible());
        $this->setPosition($version->getPosition());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());

        return $this;
    }

    /**
     * Gets the latest persisted version number for the current object
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = ChildFolderVersionQuery::create()
            ->filterByFolder($this)
            ->orderByVersion('desc')
            ->findOne($con);
        if (!$v) {
            return 0;
        }

        return $v->getVersion();
    }

    /**
     * Checks whether the current object is the latest one
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  Boolean
     */
    public function isLastVersion($con = null)
    {
        return $this->getLastVersionNumber($con) == $this->getVersion();
    }

    /**
     * Retrieves a version object for this entity and a version number
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildFolderVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildFolderVersionQuery::create()
            ->filterByFolder($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildFolderVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(FolderVersionTableMap::VERSION);

        return $this->getFolderVersions($criteria, $con);
    }

    /**
     * Compares the current object with another of its version.
     * <code>
     * print_r($book->compareVersion(1));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $versionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersion($versionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->toArray();
        $toVersion = $this->getOneVersion($versionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Compares two versions of the current object.
     * <code>
     * print_r($book->compareVersions(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $fromVersionNumber
     * @param   integer             $toVersionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersions($fromVersionNumber, $toVersionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->getOneVersion($fromVersionNumber, $con)->toArray();
        $toVersion = $this->getOneVersion($toVersionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Computes the diff between two versions.
     * <code>
     * print_r($book->computeDiff(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   array     $fromVersion     An array representing the original version.
     * @param   array     $toVersion       An array representing the destination version.
     * @param   string    $keys            Main key used for the result diff (versions|columns).
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    protected function computeDiff($fromVersion, $toVersion, $keys = 'columns', $ignoredColumns = array())
    {
        $fromVersionNumber = $fromVersion['Version'];
        $toVersionNumber = $toVersion['Version'];
        $ignoredColumns = array_merge(array(
            'Version',
            'VersionCreatedAt',
            'VersionCreatedBy',
        ), $ignoredColumns);
        $diff = array();
        foreach ($fromVersion as $key => $value) {
            if (in_array($key, $ignoredColumns)) {
                continue;
            }
            if ($toVersion[$key] != $value) {
                switch ($keys) {
                    case 'versions':
                        $diff[$fromVersionNumber][$key] = $value;
                        $diff[$toVersionNumber][$key] = $toVersion[$key];
                        break;
                    default:
                        $diff[$key] = array(
                            $fromVersionNumber => $value,
                            $toVersionNumber => $toVersion[$key],
                        );
                        break;
                }
            }
        }

        return $diff;
    }
    /**
     * retrieve the last $number versions.
     *
     * @param Integer $number the number of record to return.
     * @return PropelCollection|array \Thelia\Model\FolderVersion[] List of \Thelia\Model\FolderVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildFolderVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(FolderVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getFolderVersions($criteria, $con);
    }
    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}