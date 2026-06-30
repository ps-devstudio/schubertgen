<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Event extends AbstractEntity
{
    protected string $externalId = '';
    protected string $parentType = '';
    protected string $parentExternalId = '';
    protected string $eventType = '';
    protected string $dateText = '';
    protected int $dateYear = 0;
    protected int $dateMonth = 0;
    protected int $dateDay = 0;
    protected string $dateModifier = '';
    protected int $sortDate = 0;
    protected string $place = '';
    protected ?Place $placeRecord = null;
    protected float $latitude = 0.0;
    protected float $longitude = 0.0;
    protected string $description = '';
    protected string $sourceExternalId = '';
    protected ?Source $source = null;
    protected ?Person $person = null;
    protected ?Family $family = null;

    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $externalId): void { $this->externalId = $externalId; }
    public function getParentType(): string { return $this->parentType; }
    public function setParentType(string $parentType): void { $this->parentType = $parentType; }
    public function getParentExternalId(): string { return $this->parentExternalId; }
    public function setParentExternalId(string $parentExternalId): void { $this->parentExternalId = $parentExternalId; }
    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $eventType): void { $this->eventType = $eventType; }
    public function getDateText(): string { return $this->dateText; }
    public function setDateText(string $dateText): void { $this->dateText = $dateText; }
    public function getDateYear(): int { return $this->dateYear; }
    public function setDateYear(int $dateYear): void { $this->dateYear = $dateYear; }
    public function getDateMonth(): int { return $this->dateMonth; }
    public function setDateMonth(int $dateMonth): void { $this->dateMonth = $dateMonth; }
    public function getDateDay(): int { return $this->dateDay; }
    public function setDateDay(int $dateDay): void { $this->dateDay = $dateDay; }
    public function getDateModifier(): string { return $this->dateModifier; }
    public function setDateModifier(string $dateModifier): void { $this->dateModifier = $dateModifier; }
    public function getSortDate(): int { return $this->sortDate; }
    public function setSortDate(int $sortDate): void { $this->sortDate = $sortDate; }
    public function getPlace(): string { return $this->place; }
    public function setPlace(string $place): void { $this->place = $place; }
    public function getPlaceRecord(): ?Place { return $this->placeRecord; }
    public function setPlaceRecord(?Place $placeRecord): void { $this->placeRecord = $placeRecord; }
    public function getLatitude(): float { return $this->latitude; }
    public function setLatitude(float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): float { return $this->longitude; }
    public function setLongitude(float $longitude): void { $this->longitude = $longitude; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): void { $this->description = $description; }
    public function getSourceExternalId(): string { return $this->sourceExternalId; }
    public function setSourceExternalId(string $sourceExternalId): void { $this->sourceExternalId = $sourceExternalId; }
    public function getSource(): ?Source { return $this->source; }
    public function setSource(?Source $source): void { $this->source = $source; }
    public function getPerson(): ?Person { return $this->person; }
    public function setPerson(?Person $person): void { $this->person = $person; }
    public function getFamily(): ?Family { return $this->family; }
    public function setFamily(?Family $family): void { $this->family = $family; }
}
