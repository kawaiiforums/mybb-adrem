<?php

namespace adrem;

class ContentEntity
{
    protected ?int $id = null;
    protected string $defaultRevision = 'current';
    protected array $dataRevisions = [];
    protected array $data = [];
    /**
     * Additional data that will not be logged.
     */
    protected array $extendedData = [];
    /**
     * @var ContentEntity[]
     */
    protected array $contextContentEntities = [];

    public static function getName(): string
    {
        $chain = explode('\\', get_called_class());

        return lcfirst(end($chain));
    }

    public static function getSupportedActions(): array
    {
        static $supportedActions = null;

        if ($supportedActions === null) {
            $supportedActions = array_map('lcfirst', \adrem\getClassMethodsNamesMatching(
                get_called_class(),
                '/^trigger(.+)Action$/'
            ));
        }

        return $supportedActions;
    }

    public static function isDiscoverable(): bool
    {
        return true;
    }

    public static function acceptsContext(string $type): bool
    {
        return method_exists(static::class, 'assume' . lcfirst($type) . 'Context');
    }

    public static function providesContext(string $type): bool
    {
        return method_exists(static::class, 'get' . lcfirst($type) . 'Context');
    }

    public function __construct(?int $id = null)
    {
        if ($id !== null) {
            $this->setId($id);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getData(bool $extended = false, ?string $revision = null): ?array
    {
        if ($revision === null) {
            $revision = $this->defaultRevision;
        }

        $returnData = $this->data[$revision];

        if ($extended) {
            $returnData = array_merge($returnData, $this->extendedData[$revision]);
        }

        return $returnData;
    }

    public function setData(array $data, ?string $revision = null): void
    {
        if ($revision === null) {
            $revision = $this->defaultRevision;
        }

        $this->data[$revision] = array_merge($this->data[$revision] ?? [], $data);

        if (!in_array($revision, $this->dataRevisions)) {
            $this->dataRevisions[] = $revision;
        }
    }

    public function getDefaultRevision(): string
    {
        return $this->defaultRevision;
    }

    public function dataRevisionExists(?string $revision = null): bool
    {
        if ($revision === null) {
            $revision = $this->defaultRevision;
        }

        return in_array($revision, $this->dataRevisions);
    }

    public function assumeContext(ContentEntity $contextContentEntity): bool
    {
        $type = $contextContentEntity::getName();

        $this->contextContentEntities[$type] = $contextContentEntity;

        return $this->{'assume' . lcfirst($type) . 'Context'}($contextContentEntity);
    }

    public function getContext(string $type): ContentEntity
    {
        return $this->{'get' . lcfirst($type) . 'Context'}();
    }

    public function accessibleForCurrentUser(): bool
    {
        return true;
    }

    public function getUrl(): ?string
    {
        return null;
    }

    public function triggerActions(array $actions): array
    {
        $results = [];

        foreach ($actions as $action) {
            $results[$action] = $this->{'trigger' . ucfirst($action) . 'Action'}();
        }

        return $results;
    }
}
