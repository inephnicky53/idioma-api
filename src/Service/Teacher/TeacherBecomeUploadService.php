<?php

namespace App\Service\Teacher;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TeacherBecomeUploadService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function store(UploadedFile $file, string $subdir): string
    {
        $targetDir = $this->projectDir.'/public/uploads/teachers/'.$subdir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Cannot create upload directory "%s".', $targetDir));
        }

        $extension = $file->guessExtension() ?: 'bin';
        $filename = uniqid('', true).'.'.$extension;
        $file->move($targetDir, $filename);

        return '/uploads/teachers/'.$subdir.'/'.$filename;
    }
}
