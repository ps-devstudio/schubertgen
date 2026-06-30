<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Place extends AbstractEntity
{
    protected string $externalId = '';
    protected string $name = '';
    protected string $originalName = '';
    protected string $city = '';
    protected string $county = '';
    protected string $state = '';
    protected string $country = '';
    protected float $latitude = 0.0;
    protected float $longitude = 0.0;

    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $externalId): void { $this->externalId = $externalId; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): void { $this->originalName = $originalName; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $city): void { $this->city = $city; }
    public function getCounty(): string { return $this->county; }
    public function setCounty(string $county): void { $this->county = $county; }
    public function getState(): string { return $this->state; }
    public function setState(string $state): void { $this->state = $state; }
    public function getCountry(): string { return $this->country; }
    public function setCountry(string $country): void { $this->country = $country; }
    public function getLatitude(): float { return $this->latitude; }
    public function setLatitude(float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): float { return $this->longitude; }
    public function setLongitude(float $longitude): void { $this->longitude = $longitude; }
}
