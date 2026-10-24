<?php

declare(strict_types=1);

namespace App\Controller;

final class ProjectController extends AppController
{
    public function index(): void
    {
        $this->render('projects', [
            'pageTitle' => 'Projekty',
        ]);
    }

    public function show(int $id): void
    {
        $this->render('project', [
            'pageTitle' => 'Szczegóły projektu',
            'projectId' => $id,
        ]);
    }
}