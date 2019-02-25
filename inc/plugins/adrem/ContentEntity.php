<?php

namespace adrem;

class ContentEntity
{
    protected $id;
    protected $data = null;
    /**
     * Additional data that will not be logged.
     */
    protected $extendedData = null;

    public static function getName(): string
    {
        return lcfirst(
            end(explode('\\', get_called_class()))
        );
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

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        if ($this->data === null) {
            $this->data = $data;
        } else {
            $this->data = array_merge($this->data, $data);
        }
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
