<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Controller;

use Psr\Http\Message\ResponseInterface;
use SchubertliederPlugin\Schubertgen\Service\GenealogyDataService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class GenealogyController extends ActionController
{
    public function __construct(
        private readonly GenealogyDataService $genealogyDataService,
        private readonly PageRenderer $pageRenderer,
    ) {
    }

    public function indexAction(): ResponseInterface
    {
        $this->addAssets();
        $externalId = (string)($this->settings['defaultPersonExternalId'] ?? '96255408');
        $treeChart = $this->genealogyDataService->buildAncestorChart($externalId);
        $treeCharts = $this->genealogyDataService->buildFrontendCharts($externalId);
        $this->view->assignMultiple([
            'rootPerson' => $this->genealogyDataService->findPersonByExternalId($externalId),
            'tree' => $this->genealogyDataService->buildAncestorTree($externalId),
            'treeChartJson' => $this->json($treeChart),
            'treeCharts' => $this->jsonList($treeCharts),
        ]);

        return $this->htmlResponse();
    }

    public function listAction(): ResponseInterface
    {
        $this->addAssets();
        $externalId = (string)($this->settings['defaultPersonExternalId'] ?? '96255408');
        $treeChart = $this->genealogyDataService->buildAncestorChart($externalId);
        $treeCharts = $this->genealogyDataService->buildFrontendCharts($externalId);
        $this->view->assignMultiple([
            'persons' => $this->genealogyDataService->findAllPersons(),
            'rootPerson' => $this->genealogyDataService->findPersonByExternalId($externalId),
            'tree' => $this->genealogyDataService->buildAncestorTree($externalId),
            'treeChartJson' => $this->json($treeChart),
            'treeCharts' => $this->jsonList($treeCharts),
        ]);

        return $this->htmlResponse();
    }

    public function showAction(?string $person = null): ResponseInterface
    {
        $this->addAssets();
        $externalId = $person ?: (string)($this->request->hasArgument('person') ? $this->request->getArgument('person') : '');
        $personRow = $this->genealogyDataService->findPersonByExternalId($externalId);
        if ($personRow === null) {
            return $this->redirect('list');
        }

        $this->view->assignMultiple([
            'person' => $personRow,
            'media' => $this->genealogyDataService->findMediaByExternalId((string)$personRow['primary_media']),
            'events' => $this->genealogyDataService->findEventsForPerson($externalId),
            'parentFamilies' => $this->genealogyDataService->findParentFamiliesForPerson($externalId),
            'families' => $this->genealogyDataService->findFamiliesForPerson($externalId),
        ]);

        return $this->htmlResponse();
    }

    public function treeAction(?string $person = null): ResponseInterface
    {
        $this->addAssets();
        $externalId = $person ?: (string)($this->request->hasArgument('person') ? $this->request->getArgument('person') : ($this->settings['defaultPersonExternalId'] ?? '96255408'));
        $treeChart = $this->genealogyDataService->buildAncestorChart($externalId);
        $treeCharts = $this->genealogyDataService->buildFrontendCharts($externalId);
        $this->view->assignMultiple([
            'rootPerson' => $this->genealogyDataService->findPersonByExternalId($externalId),
            'tree' => $this->genealogyDataService->buildAncestorTree($externalId),
            'treeChartJson' => $this->json($treeChart),
            'treeCharts' => $this->jsonList($treeCharts),
        ]);

        return $this->htmlResponse();
    }

    public function mapAction(): ResponseInterface
    {
        $this->addMapAssets();
        $externalId = (string)($this->settings['defaultPersonExternalId'] ?? '96255408');
        $mapEvents = $this->genealogyDataService->buildMapEvents();
        $this->view->assignMultiple([
            'rootPerson' => $this->genealogyDataService->findPersonByExternalId($externalId),
            'mapEventsJson' => json_encode($mapEvents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'mapEventCount' => count($mapEvents),
        ]);

        return $this->htmlResponse();
    }

    private function addAssets(): void
    {
        $this->pageRenderer->addCssFile('EXT:schubertgen/Resources/Public/Css/genealogy.css');
        $this->pageRenderer->addJsFooterFile('EXT:schubertgen/Resources/Public/JavaScript/Vendor/d3.v7.min.js');
        $this->pageRenderer->addJsFooterFile('EXT:schubertgen/Resources/Public/JavaScript/genealogy.js');
    }

    private function addMapAssets(): void
    {
        $this->pageRenderer->addCssFile('EXT:schubertgen/Resources/Public/JavaScript/Vendor/leaflet/leaflet.css');
        $this->pageRenderer->addCssFile('EXT:schubertgen/Resources/Public/Css/genealogy.css');
        $this->pageRenderer->addJsFooterFile('EXT:schubertgen/Resources/Public/JavaScript/Vendor/leaflet/leaflet.js');
        $this->pageRenderer->addJsFooterFile('EXT:schubertgen/Resources/Public/JavaScript/genealogy-map.js');
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function json(?array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{title:string,json:string}>
     */
    private function jsonList(array $items): array
    {
        return array_map(
            fn (array $item): array => [
                'title' => (string)($item['title'] ?? ''),
                'json' => $this->json($item),
            ],
            $items
        );
    }
}
