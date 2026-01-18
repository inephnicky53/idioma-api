<?php

namespace App\Model;

/**
 * Interface pour les entités qui gèrent des fichiers uploadés
 * Utilisée par VichUploaderFileNormalizer pour générer les URLs absolues
 */
interface UploadedFileAwareInterface
{
    /**
     * Retourne un mapping des propriétés de fichier vers les champs Vich
     * 
     * @return array<string, string> Clé: propriété de l'entité, Valeur: champ Vich
     * 
     * Exemple:
     * [
     *     'thumbnail' => 'thumbnailFile',
     *     'videoFile' => 'videoFile'
     * ]
     */
    public function getFilePropertyMapping(): array;
}

