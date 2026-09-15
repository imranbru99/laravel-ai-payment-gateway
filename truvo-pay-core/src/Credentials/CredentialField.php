<?php

namespace Truvo\Pay\Credentials;

class CredentialField
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text', // text, password, select, textarea, boolean
        public bool $required = true,
        public bool $isSecret = true,
        public ?string $placeholder = null,
        public ?string $description = null,
        public mixed $default = null,
        public array $options = []
    ) {}

    public static function make(string $name, string $label): self
    {
        return new self(name: $name, label: $label);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    public function secret(bool $isSecret = true): self
    {
        $this->isSecret = $isSecret;
        if ($isSecret && $this->type === 'text') {
            $this->type = 'password';
        }
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'is_secret' => $this->isSecret,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'default' => $this->default,
            'options' => $this->options,
        ];
    }
}
