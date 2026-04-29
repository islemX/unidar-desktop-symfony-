<?php

namespace App\Ml\Storage;

use Rubix\ML\Learner;

class ModelRepository
{
    private string $modelsDir;

    public function __construct(string $projectDir)
    {
        $this->modelsDir = $projectDir . '/var/ml_models';
        if (!is_dir($this->modelsDir)) {
            mkdir($this->modelsDir, 0755, true);
        }
    }

    public function save(Learner $model, string $name): void
    {
        file_put_contents($this->modelPath($name), serialize($model));
    }

    public function load(string $name): Learner
    {
        $path = $this->modelPath($name);
        if (!file_exists($path)) {
            throw new \RuntimeException("Model '{$name}' not found. Run the training command first.");
        }

        /** @var Learner $model */
        $model = unserialize(file_get_contents($path));
        return $model;
    }

    public function exists(string $name): bool
    {
        return file_exists($this->modelPath($name));
    }

    public function modelPath(string $name): string
    {
        return $this->modelsDir . '/' . $name . '.rbx';
    }

    public function getMetadata(string $name): array
    {
        $metaPath = $this->modelsDir . '/' . $name . '.meta.json';
        if (!file_exists($metaPath)) {
            return [];
        }
        return json_decode(file_get_contents($metaPath), true) ?? [];
    }

    public function saveMetadata(string $name, array $metadata): void
    {
        $metaPath = $this->modelsDir . '/' . $name . '.meta.json';
        file_put_contents($metaPath, json_encode(array_merge($metadata, [
            'trained_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]), JSON_PRETTY_PRINT));
    }
}
