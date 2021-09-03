<?php

namespace Onizamov\MailChimp\Events;

use GuzzleHttp\Exception\RequestException;
use Onizamov\MailChimp\Classes\ApiClient;
use Onizamov\MailChimp\Classes\Logger;
use Onizamov\MailChimp\Orm\MailchimpSegmentMemberTable;
use Onizamov\MailChimp\Orm\MailchimpSegmentsTable;
use Onizamov\Reports\Classes\Crm\Company\Company;
use Onizamov\Reports\Classes\Crm\Company\CompanyTable;
use Onizamov\Reports\Events\CompanyReportEvents;
use MailchimpMarketing\ApiException;

class CompanyEvents
{

    public const ENTITY_TYPE_ID = 4;

    public static function onCrmCompanyDelete(int $companyId)
    {
        try {
            $arrSegmentMembers = self::getAllRowsByEntity($companyId);

            /** Удалаяем записи из таблицы */
            $arrBitrixSegmentId = array_column($arrSegmentMembers, 'bitrix_segment_id');
            $arrListMailchimpId = array_column($arrSegmentMembers, 'mailchimp_list_id');
            $arrEntityData = array_column($arrSegmentMembers, 'entity_data');

            foreach ($arrBitrixSegmentId as $bitrixSegmentId) {
                MailchimpSegmentMemberTable::delete(
                    [
                        'bitrix_segment_id' => $bitrixSegmentId,
                        'entity_id'         => $companyId,
                        'entity_type'       => CompanyEvents::ENTITY_TYPE_ID,
                    ]
                );
            }

            /** Удаляем записи из  Mailchimp*/
            foreach ($arrListMailchimpId as $key => $listId) {
                $entityData = json_decode($arrEntityData[$key], true);
                ApiClient::getClient()->lists->deleteListMember(
                    $listId,
                    md5($entityData['email'])
                );
            }
        } catch (\Exception $e) {
            Logger::log(
                $e->getCode(),
                json_encode(['COMPANY_ID' => $companyId, 'STATUS' => 'Удаление записи']),
                $e->getMessage()
            );
        }
    }

    public static function onCrmCompanyUpdate(array $fields)
    {
        if (empty($fields[Company::STATUS_CODE]) && empty($fields[Company::CATEGORY_CODE]) && empty($fields[Company::REGION_CODE])) {
            return;
        }
        $companyId = $fields['ID'];
        $arrSegmentMembers = self::getAllRowsByEntity($companyId);
        $arrBitrixSegmentId = array_column($arrSegmentMembers, 'bitrix_segment_id');
        $arrMailchimpSegmentId = array_column($arrSegmentMembers, 'mailchimp_list_id');
        $arrEntityData = array_column($arrSegmentMembers, 'entity_data');
        $extraCompanyInfo = self::getExtraCompanyInfo($companyId);
        foreach ($arrBitrixSegmentId as $key => $bitrixSegmentId) {
            $tags = [];
            $entityData = json_decode($arrEntityData[$key], true);
            MailchimpSegmentMemberTable::update(
                [
                    'bitrix_segment_id' => $bitrixSegmentId,
                    'entity_id'         => $companyId,
                    'entity_type'       => CompanyEvents::ENTITY_TYPE_ID,
                ],
                [
                    'entity_data' => json_encode(
                        array_merge(
                            $entityData,
                            [
                                'status'   => $extraCompanyInfo['CATEGORY'],
                                'category' => $extraCompanyInfo['STATUS'],
                                'region'   => $extraCompanyInfo['REGION'],
                            ]
                        )
                    ),
                ]
            );

            foreach ($extraCompanyInfo as $info) {
                if (!empty($info)) {
                    $tags[] = ["name" => $info, "status" => "active"];
                }
            }

            foreach ($entityData as $keyInfo => $info) {
                if (!empty($info) && in_array($keyInfo, ['status', 'category', 'region'])) {
                    $tags[] = ["name" => $info, "status" => "inactive"];
                }
            }

            try {
                ApiClient::getClient()->lists->updateListMemberTags(
                    $arrMailchimpSegmentId[$key],
                    md5($entityData['email']),
                    [
                        "tags" => $tags,
                    ]
                );
            } catch (\Exception $e) {
                Logger::log(
                    $e->getCode(),
                    json_encode(['COMPANY_ID' => $companyId, 'STATUS' => 'Изменение тэгов', 'TAGS' => $tags]),
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * @param int $companyId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private static function getAllRowsByEntity(int $companyId): array
    {
        return MailchimpSegmentMemberTable::getList(
            [
                'select' => ['mailchimp_list_id', 'bitrix_segment_id', 'entity_data'],
                'filter' =>
                    [
                        '=entity_id'   => $companyId,
                        '=entity_type' => CompanyEvents::ENTITY_TYPE_ID,
                    ],
            ]
        )->fetchAll();
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
}