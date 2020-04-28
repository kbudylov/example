<?php

namespace app\components\address;

use app\components\db\Manager;
use app\components\RuntimeException;
use app\components\ValidationException;
use app\modules\crud\models\Account;
use app\modules\crud\models\Room;
use app\modules\mcrud\models\AddressesIndex;
use app\modules\crud\models\Address as AddressModel;
use yii\base\Component;

/**
 * Class Address.
 */
class AddressService extends Component
{
    /**
     * @var string
     */
    public $splitAddressDelimiter = ';';

    /** @var Manager */
    protected $dbManager;

    /**
     * {@inheritdoc}
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->dbManager = \Yii::$app->get('dbManager');
    }

    /**
     * @param $address
     *
     * @return AddressModel|array|null
     *
     * @throws RuntimeException
     * @throws \app\components\db\ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function findAddressByAddressString(string $address)
    {
        $addressIndex = $this->findAddressIndexByAddressString($address);

        if ($addressIndex)
        {
            return $addressIndex->getAddress()->one($this->dbManager->createConnectionByDbId($addressIndex->db_id));
        }

        return null;
    }

    /**
     * @param $address
     *
     * @return \app\modules\crud\models\House|array|null
     *
     * @throws RuntimeException
     * @throws \app\components\db\ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function findHouseByAddressString(string $address)
    {
        $addressIndex = $this->findAddressIndexByAddressString($address);

        if ($addressIndex)
        {
            return $addressIndex->getHouse()->one($this->dbManager->createConnectionByDbId($addressIndex->db_id));
        }

        return null;
    }

    /**
     * @param string $address
     *
     * @return Room|AddressesIndex|array|null
     *
     * @throws RuntimeException
     * @throws \app\components\db\ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function findRoomByAddressString(string $address)
    {
        $addressIndex = $this->findAddressIndexByAddressString($address);

        if ($addressIndex)
        {
            return $addressIndex->getRoom()->one($this->dbManager->createConnectionByDbId($addressIndex->db_id));
        }

        return null;
    }

    /**
     * @param string $address
     *
     * @return AddressesIndex|array|null
     *
     * @throws RuntimeException
     */
    public function findAddressIndexByAddressString(string $address)
    {
        $addressParts = $this->parseAddressString($address);

        if ($addressParts)
        {
            if (!$addressParts->getCity())
            {
                throw new RuntimeException('Address part [city] not specified');
            }

            if (!$addressParts->getBuilding())
            {
                throw new RuntimeException('Address part [building] not specified');
            }

            $addressQuery = AddressesIndex::find()->where([
                'city' => $addressParts->getCity(),
                'building' => $addressParts->getBuilding(),
            ]);

            if ($addressParts->getSubject())
            {
                $addressQuery->andWhere(['subject' => $addressParts->getSubject()]);
            }

            if ($addressParts->getRegion())
            {
                $addressQuery->andWhere(['region' => $addressParts->getRegion()]);
            }

            if ($addressParts->getStreet())
            {
                $addressQuery->andWhere(['street' => $addressParts->getStreet()]);
            }

            if ($addressParts->getRoom())
            {
                $addressQuery->andWhere(['room' => $addressParts->getRoom()]);
            }

            return $addressQuery->one(\Yii::$app->db);
        }

        return null;
    }

    /**
     * @param Room     $room
     * @param int|null $db_id
     *
     * @return AddressesIndex
     *
     * @throws RuntimeException
     * @throws ValidationException
     * @throws \app\components\db\ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function createAddressIndexForRoomModel(Room $room, int $db_id = null): AddressesIndex
    {
        if (!$db_id)
        {
            if (!isset(Room::getDb()->db_id))
            {
                throw new RuntimeException('Database id not found in the room model');
            }
            $db_id = Room::getDb()->db_id;
        }

        $addressIndex = $this->findAddressIndexForRoomModel($room);

        if (!$addressIndex)
        {
            $db = $this->dbManager->createConnectionByDbId($db_id);
            $houseData = $room->getEntrance()->with('house')->asArray()->one($db);
            $accountData = Account::find()->where(['room_id' => $room->room_id])->one($db);

            if (!empty($houseData['house']['address_id']))
            {
                $addressData = AddressModel::find()->where(['address_id' => $houseData['house']['address_id']])->one($db);

                $addressString = $this->buildAddressStringFromArray([
                    'subject' => $addressData->subject,
                    'region' => $addressData->region,
                    'city' => $addressData->city,
                    'district' => $addressData->district,
                    'street' => $addressData->street,
                    'building' => $addressData->building,
                    'room' => $room->number,
                ]);

                $addressParts = $this->parseAddressString($addressString);

                $addressIndex = new AddressesIndex([
                    'db_id' => $db_id,
                    'account_id' => $accountData ? $accountData->account_id : null,
                    'address_id' => $addressData->address_id,
                    'house_id' => $room->getEntrance()->one()->house_id,
                    'room_id' => $room->room_id,
                    'subject' => $addressParts->getSubject(),
                    'region' => $addressParts->getRegion(),
                    'city' => $addressParts->getCity(),
                    'district' => $addressParts->getDistrict(),
                    'street' => $addressParts->getStreet(),
                    'building' => $addressParts->getBuilding(),
                    'room' => $addressParts->getRoom(),
                    'zipcode' => $addressData->zipcode,
                ]);

                if ($addressIndex->validate())
                {
                    if ($addressIndex->save(false))
                    {
                        return $addressIndex;
                    }

                    throw new RuntimeException('Error occurs while save address index');
                }
                else
                {
                    throw new ValidationException('Error occurs while validate address index', $addressIndex->getErrors());
                }
            }
            else
            {
                throw new RuntimeException("Address not specified for house [{$houseData['house_id']}]");
            }
        }

        return $addressIndex;
    }

    /**
     * @param Room $room
     *
     * @return AddressesIndex|array|null
     */
    public function findAddressIndexForRoomModel(Room $room)
    {
        return AddressesIndex::find()->where(['room_id' => $room->room_id])->one();
    }

    /**
     * @param array $addressArray
     *
     * @return string
     */
    public function buildAddressStringFromArray(array $addressArray): string
    {
        $addressData = [
            'subject' => $addressArray['subject'] ?? null,
            'region' => $addressArray['region'] ?? null,
            'city' => $addressArray['city'] ?? null,
            'district' => $addressArray['district'] ?? null,
            'street' => $addressArray['street'] ?? null,
            'building' => $addressArray['building'] ?? null,
            'room' => $addressArray['room'] ?? null,
        ];

        return implode($this->splitAddressDelimiter, array_values($addressData));
    }

    /**
     * @param string $address
     *
     * @return Address
     */
    public function parseAddressString(string $address)
    {
        return new Address([
            'address' => $address,
            'splitAddressDelimiter' => $this->splitAddressDelimiter,
        ]);
    }
}
