<?php

namespace NW\WebService\References\Operations\Notification;

// Нужно добавить импорт классов, здесь добавлено примерный импорт класс
use ReferencesOperation;
use Seller;
use Contractor;
use Employee;
use Status;
use MessagesClient;
use NotificationManager;
use NotificationEvents;

// Здесь изменили класс название класса
class ReturnOperation extends ReferencesOperation  
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * возвращает массив
     * @return array 
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');  // здесь нужно использовать условие, без проверки может привести к ошибке
        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType']; // здесь нужно использовать условие, без проверки может привести к ошибке
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];
        // здесь неправильно использовано приведение типов на int
        if (empty($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }
        
        // здесь неправильно использовано приведение типов на int
        if (empty($notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }
        
        // здесь неправильно использовано приведение типов на int
        $reseller = Seller::getById($resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }
        
        // здесь неправильно использовано приведение типов на int
        $client = Contractor::getById($data['clientId']);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('сlient not found!', 400);
        }
        
        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {
            $cFullName = $client->name;
        }
        
        // здесь неправильно использовано приведение типов на int
        $cr = Employee::getById($data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }
        
        // здесь неправильно использовано приведение типов на int
        $et = Employee::getById($data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                // здесь неправильно использовано приведение типов на int
                'FROM' => Status::getName($data['differences']['from']),
                'TO'   => Status::getName($data['differences']['to']),
            ], $resellerId);
        }
        
        // здесь неправильно использовано приведение типов на int
        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo'   => $client->email,
                        'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                        'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                    // здесь нужно добавлять условие проверки на 
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }
                 // здесь не определено переменный $error
            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
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