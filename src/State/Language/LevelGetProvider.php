<?php

namespace App\State\Language;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Aggregat\LanguageLevel;
use App\Idioma;

class LevelGetProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return self::getLevelData();
        }

        return self::getLevelData()[$uriVariables['id']] ?? null;
    }

    public static function getLevelData(): array
    {
        $data = [];
        array_map(function ($level) use (&$data){
            $data[] = new LanguageLevel($level);
        }, Idioma::getLevelList());

        return $data;
    }
}
