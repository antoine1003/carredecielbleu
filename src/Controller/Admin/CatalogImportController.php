<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Catalog\CatalogImporter;
use App\Service\Catalog\CatalogSourceException;
use App\Service\Catalog\ImportFileStorage;
use App\Service\Catalog\SumupCsvParser;
use App\Service\Catalog\SumupCsvSource;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Import du catalogue SumUp dans l'admin : upload → aperçu des changements → validation.
 */
#[IsGranted('ROLE_ADMIN')]
#[AdminRoute('/catalogue/import', name: 'catalog_import')]
final class CatalogImportController extends AbstractController
{
    public function __construct(
        private readonly ImportFileStorage $storage,
        private readonly SumupCsvParser $parser,
        private readonly CatalogImporter $importer,
    ) {
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        return $this->render('admin/catalog_import/index.html.twig');
    }

    #[AdminRoute('/apercu', name: 'preview', options: ['methods' => ['POST']])]
    public function preview(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('catalog_import_upload', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expiré, merci de recommencer.');

            return $this->redirectToRoute('admin_catalog_import_index');
        }

        $file = $request->files->get('csv');
        try {
            $id = $this->storage->store($file instanceof UploadedFile ? $file : null);
            $label = $file instanceof UploadedFile ? $file->getClientOriginalName() : null;
            $report = $this->importer->preview(new SumupCsvSource($this->parser, $this->storage->path($id), $label));
        } catch (CatalogSourceException $e) {
            if (isset($id)) {
                $this->storage->remove($id);
            }
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_catalog_import_index');
        }

        return $this->render('admin/catalog_import/preview.html.twig', [
            'report' => $report,
            'fileId' => $id,
            'fileLabel' => $label,
        ]);
    }

    #[AdminRoute('/valider', name: 'apply', options: ['methods' => ['POST']])]
    public function apply(Request $request): Response
    {
        $id = $request->request->getString('file');
        if (!$this->isCsrfTokenValid('catalog_import_apply'.$id, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expiré, merci de recommencer.');

            return $this->redirectToRoute('admin_catalog_import_index');
        }

        try {
            $source = new SumupCsvSource($this->parser, $this->storage->path($id), $request->request->getString('label') ?: null);
            $report = $this->importer->import($source);
        } catch (CatalogSourceException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_catalog_import_index');
        } finally {
            $this->storage->remove($id);
        }

        $this->addFlash('success', 'Import terminé : '.$report->summary());

        return $this->redirectToRoute('admin_product_index');
    }
}
