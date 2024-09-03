<?php
namespace App\Model;

interface UploadedFileAwareInterface {
    /**
     * @return array
     */
    public function getFilePropertyMapping(): array;
}