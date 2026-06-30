<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Family extends AbstractEntity
{
    protected string $externalId = '';
    protected string $husbandExternalId = '';
    protected string $wifeExternalId = '';
    protected ?Person $husband = null;
    protected ?Person $wife = null;
    protected string $marriageDateText = '';
    protected int $marriageSortDate = 0;
    protected string $marriagePlace = '';
    protected string $rawGedcom = '';

    /** @var ObjectStorage<Person> */
    protected ObjectStorage $children;

    /** @var ObjectStorage<Event> */
    protected ObjectStorage $events;

    public function __construct()
    {
        $this->children = new ObjectStorage();
        $this->events = new ObjectStorage();
    }

    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $externalId): void { $this->externalId = $externalId; }
    public function getHusbandExternalId(): string { return $this->husbandExternalId; }
    public function setHusbandExternalId(string $husbandExternalId): void { $this->husbandExternalId = $husbandExternalId; }
    public function getWifeExternalId(): string { return $this->wifeExternalId; }
    public function setWifeExternalId(string $wifeExternalId): void { $this->wifeExternalId = $wifeExternalId; }
    public function getHusband(): ?Person { return $this->husband; }
    public function setHusband(?Person $husband): void { $this->husband = $husband; }
    public function getWife(): ?Person { return $this->wife; }
    public function setWife(?Person $wife): void { $this->wife = $wife; }
    public function getMarriageDateText(): string { return $this->marriageDateText; }
    public function setMarriageDateText(string $marriageDateText): void { $this->marriageDateText = $marriageDateText; }
    public function getMarriageSortDate(): int { return $this->marriageSortDate; }
    public function setMarriageSortDate(int $marriageSortDate): void { $this->marriageSortDate = $marriageSortDate; }
    public function getMarriagePlace(): string { return $this->marriagePlace; }
    public function setMarriagePlace(string $marriagePlace): void { $this->marriagePlace = $marriagePlace; }
    public function getRawGedcom(): string { return $this->rawGedcom; }
    public function setRawGedcom(string $rawGedcom): void { $this->rawGedcom = $rawGedcom; }

    /** @return ObjectStorage<Person> */
    public function getChildren(): ObjectStorage { return $this->children; }
    /** @param ObjectStorage<Person> $children */
    public function setChildren(ObjectStorage $children): void { $this->children = $children; }
    public function addChild(Person $child): void { $this->children->attach($child); }
    public function removeChild(Person $child): void { $this->children->detach($child); }

    /** @return ObjectStorage<Event> */
    public function getEvents(): ObjectStorage { return $this->events; }
    /** @param ObjectStorage<Event> $events */
    public function setEvents(ObjectStorage $events): void { $this->events = $events; }
    public function addEvent(Event $event): void { $this->events->attach($event); }
    public function removeEvent(Event $event): void { $this->events->detach($event); }
}
