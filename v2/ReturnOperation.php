<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;

class ReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @var int|null @var
     */
    private $resellerId = null;
    /**
     * @var int|null $notificationType
     */
    private $notificationType = null;
    /**
     * @var int|null $clientId
     */
    private $clientId = null;
    /**
     * @var int|null $creatorId
     */
    private $creatorId = null;
    /**
     * @var int|null $expertId
     */
    private $expertId = null;
    /**
     * @var array $data
     */
    private $data = [];
    /**
     * @var int|false $data
     */
    private $statusFrom;
    /**
     * @var int|false $statusTo
     */
    private $statusTo;

    /**
     * @var Seller|null $reseller
     */
    private $reseller = null;

    /**
     * @var Contractor|null $client
     */
    private $client = null;

    /**
     * @var Employee|null $expert
     */
    private $creator = null;

    /**
     * @var Employee|null $expert
     */
    private $expert = null;


    /**
     * @param string $paramName
     * @return int|null
     */
    private function getIntParam(string $paramName): ?int
    {
        if(key_exists($paramName, $this->data)){
            $value = $this->data[$paramName];
            if(is_numeric($value) && (int)$value==$value)
                return $value;
        }
        return null;
    }

    /**
     * @param string $paramName
     * @return string|null
     */
    private function getStringParam(string $paramName): ?string
    {
        return $this->data[$paramName] ?? null;
    }

    /**
     * @param string|null $errorMessage
     * @return bool
     * @throws Exception
     */
    private function initFields(?string &$errorMessage): bool
    {
        $errorMessage = '';
        $this->data = $this->getRequest('data');
        $this->resellerId = $this->getIntParam('resellerId');
        if (is_null($this->resellerId)) {
            $errorMessage = 'Empty resellerId';
            return false;
        }
        $this->notificationType = $this->getIntParam('notificationType');
        if (is_null($this->notificationType)) {
            throw new Exception('Empty notificationType', 400);
        }
        $this->clientId = $this->getIntParam('clientId');
        if (is_null($this->clientId)) {
            throw new Exception('Empty clientId', 400);
        }
        $this->creatorId = $this->getIntParam('creatorId');
        if (is_null($this->creatorId)) {
            throw new Exception('Empty creatorId', 400);
        }
        $this->expertId = $this->getIntParam('expertId');
        if (is_null($this->expertId)) {
            throw new Exception('Empty expertId', 400);
        }
        $this->statusFrom = false;
        $this->statusTo = false;
        if(key_exists('differences',$this->data) && is_array($this->data['differences'])){
            $from = $this->data['differences']['from'] ?? false;
            $to = $this->data['differences']['to'] ?? false;
            if(Status::check($from) && Status::check($to)) {
                $this->statusFrom = $from;
                $this->statusTo = $to;
            }
        }
        return true;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function initContractors(): void
    {
        $this->reseller = Seller::getById($this->resellerId);
        if ($this->reseller === null) {
            throw new Exception('Seller not found!', 400);
        }

        $this->client = Contractor::getById($this->clientId);
        if ($this->client === null || $this->client->getType() !== Contractor::TYPE_CUSTOMER || !isset($this->client->Seller) || $this->client->Seller->getId() !== $this->resellerId) {
            throw new Exception('Client not found!', 400);
        }

        $this->creator = Employee::getById($this->creatorId);
        if ($this->creator === null) {
            throw new Exception('Creator not found!', 400);
        }

        $this->expert = Employee::getById($this->expertId);
        if ($this->expert === null) {
            throw new Exception('Expert not found!', 400);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function doOperation(): array
    {
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
            'haveError' =>  false,
            'errorMessage' =>  false,
        ];

        if (!$this->initFields($errorMessage)) {
            $result['haveError'] = true;
            $result['errorMessage'] = $errorMessage;
            return $result;
        }
        $this->initContractors();


        $differences = '';
        if ($this->notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $this->resellerId);
        } elseif ($this->notificationType === self::TYPE_CHANGE && $this->statusFrom && $this->statusTo) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName($this->statusFrom),
                'TO' => Status::getName($this->statusTo)
            ], $this->resellerId);
        }

        $templateData = [
            'COMPLAINT_ID'       => $this->getIntParam('complaintId'),
            'COMPLAINT_NUMBER'   => $this->getStringParam('complaintNumber'),
            'CREATOR_ID'         => $this->creatorId,
            'CREATOR_NAME'       => $this->creator->getFullName(),
            'EXPERT_ID'          => $this->expertId,
            'EXPERT_NAME'        => $this->expert->getFullName(),
            'CLIENT_ID'          => $this->clientId,
            'CLIENT_NAME'        => $this->client->getFullName(),
            'CONSUMPTION_ID'     => $this->getIntParam('consumptionId'),
            'CONSUMPTION_NUMBER' => $this->getStringParam('consumptionNumber'),
            'AGREEMENT_NUMBER'   => $this->getStringParam('agreementNumber'),
            'DATE'               => $this->getStringParam('date'),
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($this->resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($this->resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                $res = MessagesClient::sendMessage([
                        0 => [ // MessageTypes::EMAIL
                               'emailFrom' => $emailFrom,
                               'emailTo'   => $email,
                               'subject'   => __('complaintEmployeeEmailSubject', $templateData, $this->resellerId),
                               'message'   => __('complaintEmployeeEmailBody', $templateData, $this->resellerId),
                        ],
                    ]
                    , $this->resellerId
                    , $this->client->getId()
                    , $this->notificationType === self::TYPE_CHANGE? NotificationEvents::CHANGE_RETURN_STATUS :NotificationEvents::NEW_RETURN_STATUS
                    , $this->statusTo
                );
                if($res)
                    $result['notificationEmployeeByEmail'] = true;
                else{
                    $result['haveError'] = true;
                    $result['errorMessage'] .= " Failed to send a message to an employee ".$email.".";
                }
            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($this->notificationType === self::TYPE_CHANGE) {
            if (!empty($emailFrom) && !empty($this->client->getEmail())) {
                $res = MessagesClient::sendMessage([
                        0 => [ // MessageTypes::EMAIL
                               'emailFrom' => $emailFrom,
                               'emailTo'   => $this->client->getEmail(),
                               'subject'   => __('complaintClientEmailSubject', $templateData, $this->resellerId),
                               'message'   => __('complaintClientEmailBody', $templateData, $this->resellerId),
                        ],
                    ]
                    , $this->resellerId
                    , $this->client->getId()
                    , NotificationEvents::CHANGE_RETURN_STATUS
                    , $this->statusTo
                );
                if($res)
                    $result['notificationClientByEmail'] = true;
                else{
                    $result['haveError'] = true;
                    $result['errorMessage'] .= " Failed to send a message to a client ".$this->client->getEmail().".";
                }
            }

            if ($this->client->haveMobile()) {
                $res = NotificationManager::send($this->resellerId, $this->client->getId(), NotificationEvents::CHANGE_RETURN_STATUS, $this->statusTo, $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
