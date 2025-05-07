
    /**
     * attribute to determine if this object has previously been saved.
     *
     * @var bool
     */
    protected bool $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     *
     * @var bool
     */
    protected bool $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     *
     * @var array<bool>
     */
    protected array $modifiedColumns = [];

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     *
     * @var array<mixed>
     */
    protected array $virtualColumns = [];
