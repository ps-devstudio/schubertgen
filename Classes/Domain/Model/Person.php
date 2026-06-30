<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Person extends AbstractEntity
{
    protected string $externalId = '';
    protected string $slug = '';
    protected string $fullName = '';
    protected string $givenName = '';
    protected string $surname = '';
    protected string $gender = '';
    protected string $birthDateText = '';
    protected int $birthSortDate = 0;
    protected string $birthPlace = '';
    protected string $deathDateText = '';
    protected int $deathSortDate = 0;
    protected string $deathPlace = '';
    protected string $primaryMedia = '';
    protected ?FileReference $primaryImage = null;
    protected string $notes = '';
    protected string $rawGedcom = '';

    /** @var ObjectStorage<Event> */
    protected ObjectStorage $events;

    public function __construct()
    {
        $this->events = new ObjectStorage();
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function setGivenName(string $givenName): void
    {
        $this->givenName = $givenName;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): void
    {
        $this->surname = $surname;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function setGender(string $gender): void
    {
        $this->gender = $gender;
    }

    public function getBirthDateText(): string
    {
        return $this->birthDateText;
    }

    public function setBirthDateText(string $birthDateText): void
    {
        $this->birthDateText = $birthDateText;
    }

    public function getBirthSortDate(): int
    {
        return $this->birthSortDate;
    }

    public function setBirthSortDate(int $birthSortDate): void
    {
        $this->birthSortDate = $birthSortDate;
    }

    public function getBirthPlace(): string
    {
        return $this->birthPlace;
    }

    public function setBirthPlace(string $birthPlace): void
    {
        $this->birthPlace = $birthPlace;
    }

    public function getDeathDateText(): string
    {
        return $this->deathDateText;
    }

    public function setDeathDateText(string $deathDateText): void
    {
        $this->deathDateText = $deathDateText;
    }

    public function getDeathSortDate(): int
    {
        return $this->deathSortDate;
    }

    public function setDeathSortDate(int $deathSortDate): void
    {
        $this->deathSortDate = $deathSortDate;
    }

    public function getDeathPlace(): string
    {
        return $this->deathPlace;
    }

    public function setDeathPlace(string $deathPlace): void
    {
        $this->deathPlace = $deathPlace;
    }

    public function getPrimaryMedia(): string
    {
        return $this->primaryMedia;
    }

    public function setPrimaryMedia(string $primaryMedia): void
    {
        $this->primaryMedia = $primaryMedia;
    }

    public function getPrimaryImage(): ?FileReference
    {
        return $this->primaryImage;
    }

    public function setPrimaryImage(?FileReference $primaryImage): void
    {
        $this->primaryImage = $primaryImage;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    public function getRawGedcom(): string
    {
        return $this->rawGedcom;
    }

    public function setRawGedcom(string $rawGedcom): void
    {
        $this->rawGedcom = $rawGedcom;
    }

    /**
     * @return ObjectStorage<Event>
     */
    public function getEvents(): ObjectStorage
    {
        return $this->events;
    }

    /**
     * @param ObjectStorage<Event> $events
     */
    public function setEvents(ObjectStorage $events): void
    {
        $this->events = $events;
    }

    public function addEvent(Event $event): void
    {
        $this->events->attach($event);
    }

    public function removeEvent(Event $event): void
    {
        $this->events->detach($event);
    }

    public function getBirthEvent(): ?Event
    {
        return $this->getEventByType('birth');
    }

    public function getDeathEvent(): ?Event
    {
        return $this->getEventByType('death');
    }

    public function getChristeningEvent(): ?Event
    {
        return $this->getEventByType('christening');
    }

    private function getEventByType(string $eventType): ?Event
    {
        foreach ($this->events as $event) {
            if ($event->getEventType() === $eventType) {
                return $event;
            }
        }

        return null;
    }
}
