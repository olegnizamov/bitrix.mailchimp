<?php

namespace Onizamov\MailChimp\Orm;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Objectify\Collection;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\SystemException;
use Exception;
use Onizamov\MailChimp\Classes\ApiClient;
use Onizamov\MailChimp\Classes\Logger;
use Onizamov\MailChimp\Events\SegmentEvents;


/**
 * Class MailchimpSegmentMemberTable
 * @package Onizamov\MailChimp\Orm
 **/
class MailchimpSegmentMemberTable extends DataManager
{
    /**
     * Возвращает название таблицы.
     *
     * @return string
     */
    public static function getTableName(): string
    {
        return 'mailchimp_segment_member';
    }

    /**
     * Возвращает карту сущности.
     *
     * @return array
     * @throws SystemException
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'bitrix_segment_id',
                [
                    'primary' => true,
                    'title'   => 'bitrix_segment_id',
                ]
            ),
            new StringField(
                'mailchimp_list_id',
                [
                    'title' => 'mailchimp_list_id',
                ]
            ),
            new StringField(
                'mailchimp_segment_id',
                [
                    'title' => 'mailchimp_list_id',
                ]
            ),
            new IntegerField(
                'entity_id',
                [
                    'primary' => true,
                    'title'   => 'entity_id',
                ]
            ),
            new IntegerField(
                'entity_type',
                [
                    'primary' => true,
                    'title'   => 'entity_type',
                ]
            ),
            new StringField(
                'entity_abbreviation',
                [
                    'title' => 'entity_abbreviation',
                ]
            ),
            new StringField(
                'entity_mailchimp_id',
                [
                    'title' => 'entity_mailchimp_id',
                ]
            ),
            new StringField(
                'entity_mailchimp_unique_email_id',
                [
                    'title' => 'entity_mailchimp_unique_email_id',
                ]
            ),
            new StringField(
                'entity_mailchimp_web_id',
                [
                    'title' => 'entity_mailchimp_web_id',
                ]
            ),
            new TextField(
                'entity_data',
                [
                    'title' => 'entity_data',
                ]
            ),
        ];
    }

    /**
     * Метод удаления строк по Id Битрикс Сегмента и фильтру
     *
     * @param int $bitrixSegmentId
     * @param array $filter
     * @return null
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function deleteRows(int $bitrixSegmentId, array $filter = [])
    {
        $mailchimpSegmentMemberCollection = self::getRows($bitrixSegmentId, $filter);
        foreach ($mailchimpSegmentMemberCollection as $mailchimpSegmentMember) {
            unset($mailchimpSegmentMember['entity_abbreviation']);
            self::delete(array_merge($mailchimpSegmentMember, ['bitrix_segment_id' => $bitrixSegmentId]));
        }
    }

    /**
     * Метод удаления строк по Id Битрикс Сегмента и фильтру
     *
     * @param int $bitrixSegmentId
     * @param array $filter
     * @return Collection|bool|mixed|string|null
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getRows(int $bitrixSegmentId, array $filter = [])
    {
        return self::getList(
            [
                'select' => ['entity_id', 'entity_type', 'entity_abbreviation'],
                'filter' => array_merge(
                    [
                        '=bitrix_segment_id' => $bitrixSegmentId,
                    ],
                    $filter
                ),
            ]
        )->fetchAll();
    }


    /**
     * Метод удаления строк по Id Битрикс Сегмента и фильтру
     *
     * @param int $bitrixSegmentId
     * @param array $filter
     * @return Collection|bool|mixed|string|null
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function mailchimpSynchronization()
    {
        /** Получаем коллекцию, где у элементов пустые
         * entity_mailchimp_id - идентификатор в mailchimp
         */
        $mailchimpSegmentMemberCollection = self::query()
            ->setSelect(
                [
                    'mailchimp_list_id',
                    'bitrix_segment_id',
                    'mailchimp_segment_id',
                    'entity_data',
                    'mailchimp_list_id',
                ]
            )
            ->where('entity_mailchimp_id', '')
            ->where('entity_mailchimp_unique_email_id', '')
            ->where('entity_mailchimp_web_id', '')
            ->fetchCollection();

        $arrSegmentsMember = [];
        //TODO отправляем по одному, т.к нам важны эти данные. - возможно потом переписать на batch запрос
        //TODO оптимизировать запросы на чтение в базу - если сегмент не изменился - не делаем запрос.
        foreach ($mailchimpSegmentMemberCollection as $mailchimpSegmentMember) {
            $mailchimpListId = $mailchimpSegmentMember->get('mailchimp_list_id');
            $bitrixSegmentId = $mailchimpSegmentMember->get('bitrix_segment_id');
            $entityData = json_decode($mailchimpSegmentMember->getEntityData(), true);
            $email = $entityData['email'];
            if (empty($mailchimpListId) || empty($email)) {
                continue;
            }
            if (!empty($arrSegmentsMember[$mailchimpListId][$email])) {
                /**Устанавливаем демо данные, чтобы данный элемент заново не попадал в выборку*/
                self::setDefaultMailchimpInfo($mailchimpSegmentMember);
                continue;
            }
            $arrSegmentsMember[$mailchimpListId][$email] = $mailchimpSegmentMember;
            try {
                /** Отправить элемент на добавление в list */
                [$entityMailchimpId, $entityMailchimpUniqueEmailId, $entityMailchimpWebId] = self::addListMember(
                    $mailchimpListId,
                    $entityData
                );
            } catch (Exception $e) {
                //Ничего не делаем, в следующий раз попробуем добавить данный элемент
                continue;
            }

            /** Создаем статический сегмент(тэг) , если он не создан.*/
            $mailchimpSegmentObj = MailchimpSegmentsTable::getByPrimary(
                $bitrixSegmentId,
                [
                    'select' => ['UF_MAILCHIMP_SEGMENT_ID', 'UF_MAILCHIMP_LIST_ID', 'UF_SEGMENT_TITLE'],
                ]
            )->fetchObject();

            if (!empty($mailchimpSegmentObj) && empty($mailchimpSegmentObj->get('UF_MAILCHIMP_SEGMENT_ID'))) {
                [$mailchimpSegmentId, $dynamicMailchimpSegmentId] = self::addSegment(
                    $mailchimpSegmentObj,
                    $entityData['email']
                );

                MailchimpSegmentsTable::update(
                    $bitrixSegmentId,
                    [
                        'UF_MAILCHIMP_DYNAMIC_SEGMENT_ID' => $dynamicMailchimpSegmentId,
                        'UF_MAILCHIMP_SEGMENT_ID'         => $mailchimpSegmentId,
                    ]
                );
            } elseif (!empty($mailchimpSegmentObj->get('UF_MAILCHIMP_SEGMENT_ID'))) {
                self::addSegmentMember(
                    $mailchimpSegmentObj,
                    $entityData['email']
                );

                $mailchimpSegmentId = $mailchimpSegmentObj->get('UF_MAILCHIMP_SEGMENT_ID');
            }

            $mailchimpSegmentMember->setEntityMailchimpId($entityMailchimpId);
            $mailchimpSegmentMember->setEntityMailchimpUniqueEmailId($entityMailchimpUniqueEmailId);
            $mailchimpSegmentMember->setEntityMailchimpWebId($entityMailchimpWebId);
            $mailchimpSegmentMember->setMailchimpSegmentId($mailchimpSegmentId);
        }

        $mailchimpSegmentMemberCollection->save();
    }

    /**
     * Событие до удаления элемента из таблицу.
     *
     * @param Event $event
     */
    public
    static function onBeforeDelete(
        $event
    ) {
        [$mailchimpListId, $mailchimpSegmentId, $entityData] = self::getOrmObjValues($event);
        /** Если не установлен параметр сегмента и списка из маилчимпа */
        if (empty($mailchimpListId) || empty($mailchimpSegmentId) || empty($entityData['email'])) {
            return;
        }

        try {
            ApiClient::getClient()->lists->removeSegmentMember(
                $mailchimpListId,
                $mailchimpSegmentId,
                md5($entityData['email'])
            );
        } catch (Exception $e) {
            Logger::log(
                $e->getCode(),
                json_encode([$mailchimpListId, $mailchimpSegmentId, $entityData]),
                $e->getMessage()
            );
            return;
        }
    }

    /**
     * Получаем значения объекта
     *
     * @param Event $event
     * @return array
     */
    private
    static function getOrmObjValues(
        Event $event
    ): array {
        try {
            $arrPrimariesKeys = $event->getParameter('primary');
            $mailchimpSegmentMemberObj = self::getByPrimary(
                $arrPrimariesKeys,
                [
                    'select' => ['mailchimp_list_id', 'mailchimp_segment_id', 'entity_data'],
                ]
            )->fetchObject();

            $mailchimpListId = $mailchimpSegmentMemberObj->get('mailchimp_list_id');
            $mailchimpSegmentId = $mailchimpSegmentMemberObj->get('mailchimp_segment_id');
            $entityData = json_decode($mailchimpSegmentMemberObj->get('entity_data'), true);
        } catch (Exception $e) {
            Logger::log($e->getCode(), json_encode($event->getParameter('primary')), $e->getMessage());
            return [];
        }
        return [$mailchimpListId, $mailchimpSegmentId, $entityData];
    }

    /**
     * @param EntityObject|null $mailchimpSegmentObj
     * @param $email
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws SystemException
     */
    private
    static function addSegmentMember(
        ?EntityObject $mailchimpSegmentObj,
        $email
    ): void {
        $listId = $mailchimpSegmentObj->get('UF_MAILCHIMP_LIST_ID');
        $mailchimpSegmentId = $mailchimpSegmentObj->get('UF_MAILCHIMP_SEGMENT_ID');
        /** отправить элемент на добавление в статический сегмент(тэг) */
        ApiClient::getClient()->lists->createSegmentMember(
            $listId,
            $mailchimpSegmentId,
            [
                "email_address" => $email,
            ]
        );
    }

    /**
     * Проверка элемента на наличие в других секциях. Если в других секциях данный элемент есть,
     * передаются его параметры. Если данных нет - создается элемент в mailchimp
     *
     * @param $mailchimpListId
     * @param $entityData
     * @return mixed
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    private
    static function addListMember(
        $mailchimpListId,
        $entityData
    ) {
        try {
            $response = ApiClient::getClient()->lists->getListMember($mailchimpListId, md5($entityData['email']));
        } catch (Exception $e) {
            $response = ApiClient::getClient()->lists->addListMember(
                $mailchimpListId,
                [
                    "email_address" => $entityData['email'],
                    "status"        => "subscribed",
                    "email_type"    => "html",
                    "language"      => "russian",
                    "vip"           => false,
                    'merge_fields'  =>
                        array_diff(
                            [
                                'PHONE' => $entityData['phone'],
                                'FNAME' => $entityData['name'],
                                'LNAME' => $entityData['surname'],
                            ],
                            ['']
                        ),
                    "tags"          =>
                        array_diff(
                            [
                                $entityData['entity_type'],
                                $entityData['status'],
                                $entityData['category'],
                                $entityData['region'],
                            ],
                            ['']
                        ),
                ]
            );
        }

        return [$response->id, $response->unique_email_id, $response->web_id];
    }

    /**
     * @param EntityObject $mailchimpSegmentObj
     * @param $email
     * @return array
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws SystemException
     */
    private
    static function addSegment(
        EntityObject $mailchimpSegmentObj,
        $email
    ): array {
        $listId = $mailchimpSegmentObj->get('UF_MAILCHIMP_LIST_ID');
        $segmentTitle = $mailchimpSegmentObj->get('UF_SEGMENT_TITLE');
        /** Добавляем тэг */
        $response = ApiClient::getClient()->lists->createSegment(
            $listId,
            [
                'name'           => $segmentTitle . SegmentEvents::STATIC_SEGMENT_PREFIX,
                //Причина, что сегменты не могут совпадать по имени
                'static_segment' => [$email],
            ]
        );
        $mailchimpSegmentId = $response->id;

        /** Добавляем динамический сегмент */
        $response = ApiClient::getClient()->lists->createSegment(
            $listId,
            [
                'name'    => $segmentTitle,
                'options' => [
                    'match'      => 'any',
                    'conditions' => [
                        [
                            'field'          => 'static_segment',
                            'condition_type' => 'StaticSegment',
                            'op'             => 'static_is',
                            'value'          => $mailchimpSegmentId,
                        ],
                    ],
                ],
            ]
        );

        $dynamicMailchimpSegmentId = $response->id;
        return [$mailchimpSegmentId, $dynamicMailchimpSegmentId];
    }

    /**
     * @param $mailchimpSegmentMember
     */
    private
    static function setDefaultMailchimpInfo(
        &$mailchimpSegmentMember
    ): void {
        $mailchimpSegmentMember->setEntityMailchimpId('-');
        $mailchimpSegmentMember->setEntityMailchimpUniqueEmailId('-');
        $mailchimpSegmentMember->setEntityMailchimpWebId('-');
    }
}