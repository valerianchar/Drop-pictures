<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les tests rendent des pages Inertia sans construire les assets : Vite est
     * neutralisé, sinon la CI échoue sur un manifest absent. Les vrais assets
     * sont, eux, construits et vérifiés par la construction de l'image.
     *
     * Le disque local est remplacé par un disque jetable : aucun test n'écrit
     * dans storage/app.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Storage::fake('local');
    }
}
