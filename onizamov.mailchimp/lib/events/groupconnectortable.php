<?php

namespace Onizamov\MailChimp\Events;

use Bitrix\Crm\ContactTable;
use Bitrix\Sender\Connector\Base;
use Bitrix\Sender\Connector\Manager;
use Bitrix\Sender\UI\PageNavigation;
use Onizamov\MailChimp\Classes\Logger;
use Onizamov\MailChimp\Orm\MailchimpSegmentMemberTable;
use Onizamov\MailChimp\Orm\MailchimpSegmentsTable;
use Onizamov\Reports\Classes\Crm\Company\Company;
use Onizamov\Reports\Classes\Crm\Company\CompanyTable;

/**
 * Class GroupConnectorTable
 *
 * @package Onizamov\MailChimp\Events
 **/
class GroupConnectorTable extends \Bitrix\Sender\GroupConnectorTable
{

    /**
     * Событие добавление получателей
     * @param \Bitrix\Main\ORM\Event $event
     */
    public static function onAfterAdd($event)
    {
        $endpoint = $event->getParameter('fields')['ENDPOINT'];
        if (empty($endpoint)) {
            return;
        }
        $segmentId = $event->getParameter('primary')['GROUP_ID'];

        $connector = Manager::getConnector($endpoint);
        $connector = self::prepareConnector($connector, $endpoint['FIELDS']);
        $result = $connector->getResult();

        if ($result->getSelectedRowsCount() > 0) {
            try {
                while ($item = $result->fetchPlain()) {
                    $item['abbreviation'] = self::getEntityAbbreviation($item);
                    $rows[$item['abbreviation']] = $item;
                }

                MailchimpSegmentMemberTable::deleteRows(
                    $segmentId,
                    [
                        '!=entity_abbreviation' => array_column($rows, 'abbreviation'),
                    ]
                );

                $rowsInDB = MailchimpSegmentMemberTable::getRows(
                    $segmentId,
                    [
                        '=entity_abbreviation' => array_column($rows, 'abbreviation'),
                    ]
                );

                $entityExistInDb = array_column($rowsInDB, 'entity_abbreviation');
                $mailchimpInfo = MailchimpSegmentsTable::getRowById($segmentId);

                foreach ($rows as $key => $item) {
                    if (empty($item['EMAIL']) || in_array($key, $entityExistInDb)) {
                        continue;
                    }
                    $extraCompanyInfo = [];
                    if ($item['CRM_ENTITY_TYPE_ID'] == 4) {
                        $extraCompanyInfo = self::getExtraCompanyInfo($item['CRM_ENTITY_ID']);
                    } else {
                        $contactObj = ContactTable::getById($item['CRM_ENTITY_ID'])->fetchObject();
                        $item['LASTNAME'] = $contactObj->getLastName();
                    }

                    self::addMailchimpSegmentMember(
                        $segmentId,
                        $mailchimpInfo['UF_MAILCHIMP_LIST_ID'],
                        $item,
                        $extraCompanyInfo
                    );
                }
            } catch (\Exception $e) {
                Logger::log(
                    $e->getCode(),
                    json_encode(
                        [
                            segmentId,
                            $mailchimpInfo['UF_MAILCHIMP_LIST_ID'],
                            $item,
                            $extraCompanyInfo,
                        ]
                    ),
                    $e->getMessage()
                );
                return;
            }
        }
    }

    /**
     * Получение предустановленных данных - Колонок
     *
     * @return array[]
     */
    private static function getColumns(): array
    {
        return [
            [
                "id"      => "NAME",
                "default" => true,
            ],
            [
                "id"      => "EMAIL",
                "default" => true,
            ],
            [
                "id"      => "PHONE",
                "default" => true,
            ],
        ];
    }

    /**
     * Получение предустановленных данных - Фильтра
     *
     * @return array[]
     */
    private static function getFilters(): array
    {
        return [
            [
                "id"      => "NAME",
                "default" => true,
            ],
            [
                "id"      => "EMAIL",
                "default" => true,
            ],
            [
                "id"      => "PHONE",
                "default" => true,
            ],
            [
                "id"      => "SENDER_RECIPIENT_TYPE_ID",
                "default" => true,
                "type"    => "list",
            ],
        ];
    }

    /**
     * Получения предустановленного коннектора
     *
     * @param Base|null $connector
     * @param $fields
     * @return Base
     */
    private static function prepareConnector(
        ?Base $connector,
        $fields
    ): Base {
        $columns = self::getColumns();
        $filters = self::getFilters();
        $connector->getResultView()->modifyColumns($columns);
        $connector->getResultView()->modifyFilter($filters);
        $nav = new PageNavigation('page-sender-connector-result-list');
        $nav->allowAllRecords(true);
        $connector->getResultView()->setNav($nav);
        $connector->setFieldValues($fields);
        return $connector;
    }

    /**
     * @param int $crmEntityId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private static function getExtraCompanyInfo(int $crmEntityId): array
    {
        $extraCompanyInfo = [];
        $companyPrepareCollection = CompanyTable::query()
            ->setSelect(
                [
                    Company::COMPANY_CODE,
                    CompanyTable::CATEGORY,
                    CompanyTable::STATUS,
                    CompanyTable::REGION,
                ]
            );
        $companyPrepareCollection->where(Company::COMPANY_CODE, $crmEntityId);
        $companyObj = $companyPrepareCollection->fetchObject();
        if (!empty($companyObj)) {
            if (!empty($companyObj->get(CompanyTable::CATEGORY))) {
                $extraCompanyInfo['CATEGORY'] = $companyObj->get(CompanyTable::CATEGORY)->getName();
            }
            if (!empty($companyObj->get(CompanyTable::STATUS))) {
                $extraCompanyInfo['STATUS'] = $companyObj->get(CompanyTable::STATUS)->getValue();
            }

            if (!empty($companyObj->get(CompanyTable::REGION))) {
                $extraCompanyInfo['REGION'] = $companyObj->get(CompanyTable::REGION)->getName();
            }
        }
        return $extraCompanyInfo;
    }

    /**
     * Получение аббревиатуры сущности.
     *
     * @param array|null $item
     * @return string
     */
    private static function getEntityAbbreviation(?array $item): string
    {
        return (($item['CRM_ENTITY_TYPE_ID'] == 4) ? 'COMPANY_' : 'CONTACT_') . $item['CRM_ENTITY_ID'];
    }

    /**
     * Добавление записи в mailchimp_segment_member
     *
     * @param $segmentId
     * @param $UF_MAILCHIMP_LIST_ID
     * @param $item
     * @param array $extraCompanyInfo
     * @throws \Exception
     */
    private static function addMailchimpSegmentMember(
        $segmentId,
        $UfMailchimpListId,
        $item,
        array $extraCompanyInfo
    ): void {
        MailchimpSegmentMemberTable::add(
            [
                'bitrix_segment_id'   => $segmentId,
                'mailchimp_list_id'   => $UfMailchimpListId,
                'entity_id'           => $item['CRM_ENTITY_ID'],
                'entity_type'         => $item['CRM_ENTITY_TYPE_ID'],
                'entity_abbreviation' => $item['abbreviation'],
                'entity_data'         => json_encode(
                    [
                        'name'        => $item['NAME'],
                        'surname'     => $item['LASTNAME'],
                        'email'       => $item['EMAIL'],
                        'phone'       => $item['PHONE'],
                        'entity_type' => ($item['CRM_ENTITY_TYPE_ID'] == 4) ? 'Компания' : 'Контакт',
                        'status'      => $extraCompanyInfo['CATEGORY'],
                        'category'    => $extraCompanyInfo['STATUS'],
                        'region'      => $extraCompanyInfo['REGION'],
                    ]
                ),
            ]
        );
    }
}