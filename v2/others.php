<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    private $id;
    private $type;
    private $name;
    private $email;

    private $haveMobile;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return false
     */
    public function haveMobile(): bool
    {
        return $this->haveMobile;
    }


    public function __construct(int $resellerId)
    {
        $this->id = $resellerId;
        $this->name = '';
        $this->email = '';
        $this->type = Contractor::TYPE_CUSTOMER;
        $this->haveMobile = false;
    }

    /**
     * @param int $resellerId
     * @return Contractor|null
     */
    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class Status
{
    const COMPLETED = 'Undefined';
    const PENDING = 'Pending';
    const REJECTED = 'Rejected';
    const NAMES = [
        0 => 'Completed',
        1 => 'Pending',
        2 => 'Rejected',
    ];
    public static function getName(int $id): string
    {
        switch($id){
            case 0:
                return Status::COMPLETED;
            case 2:
                return Status::REJECTED;
        }
        return Status::PENDING;
    }

    public static function check(mixed $id): bool
    {
        return is_numeric($id) && key_exists($id, Status::NAMES);
    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName):array
    {
        $data = $_REQUEST[$pName] ?? [];
        return is_array($data)? $data : [];
    }
}

function getResellerEmailFrom(int $resellerId) : string
{
    return 'contractor@example.com';
}

function getEmailsByPermit(int $resellerId, string $event) : array
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}