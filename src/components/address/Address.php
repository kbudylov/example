<?php

namespace app\components\address;

use app\components\RuntimeException;
use yii\base\Component;

/**
 * Class AddressParts.
 */
class Address extends Component
{
    /** @var string */
    public $splitAddressDelimiter = ';';

    /** @var string */
    protected $address;

    /** @var string|null */
    protected $subject;

    /** @var string|null */
    protected $region;

    /** @var string|null */
    protected $city;

    /** @var string|null */
    protected $district;

    /** @var string|null */
    protected $street;

    /** @var string|null */
    protected $building;

    /** @var string|null */
    protected $room;

    /**
     * @throws RuntimeException
     */
    public function init()
    {
        parent::init();

        if (!empty($this->address))
        {
            $this->parseAddressString($this->address, $this->splitAddressDelimiter);
        }
    }

    /**
     * @param string $address
     *
     * @throws RuntimeException
     */
    public function setAddress(string $address)
    {
        if (!empty($address))
        {
            $this->address = $address;
            $this->parseAddressString($address, $this->splitAddressDelimiter);
        }
        else
        {
            throw new RuntimeException('Address string is empty');
        }
    }

    /**
     * @param string $address
     * @param string $delimiter
     *
     * @throws RuntimeException
     */
    protected function parseAddressString(string $address, string $delimiter)
    {
        $_address = preg_split('/' . $delimiter . '/', $address);
        $addressPartsData = $this->fillAddressPartsData($_address);
        $this->subject = $addressPartsData['subject'] ?? null;
        $this->region = $addressPartsData['region'] ?? null;
        $this->city = $addressPartsData['city'] ?? null;
        $this->district = $addressPartsData['district'] ?? null;
        $this->street = $addressPartsData['street'] ?? null;
        $this->building = $addressPartsData['building'] ?? null;
        $this->room = $addressPartsData['room'] ?? null;
    }

    /**
     * @param array $addressParts
     *
     * @return array
     *
     * @throws RuntimeException
     */
    protected function fillAddressPartsData(array $addressParts): array
    {
        $_addressPartsCount = count($addressParts);

        if ($_addressPartsCount < 4)
        {
            throw new RuntimeException('Not enough address data found');
        }
        elseif (4 === $_addressPartsCount)
        {
            //expecting: city,street,house,room
            $addressPartsData = [
                'city' => $addressParts[0],
                'street' => $addressParts[1],
                'building' => $addressParts[2],
                'room' => $addressParts[3],
            ];
        }
        elseif (5 === $_addressPartsCount)
        {
            //expecting: region,city,street,house,room
            $addressPartsData = [
                'region' => $addressParts[0],
                'city' => $addressParts[1],
                'street' => $addressParts[2],
                'building' => $addressParts[3],
                'room' => $addressParts[4],
            ];
        }
        elseif (6 === $_addressPartsCount)
        {
            //expecting: subject,region,city,street,house,room
            $addressPartsData = [
                'subject' => $addressParts[0],
                'region' => $addressParts[1],
                'city' => $addressParts[2],
                'street' => $addressParts[3],
                'building' => $addressParts[4],
                'room' => $addressParts[5],
            ];
        }
        else
        {
            //expecting: subject,region,district, city,street,house,room
            $addressPartsData = [
                'subject' => $addressParts[0],
                'region' => $addressParts[1],
                'city' => $addressParts[2],
                'district' => $addressParts[3],
                'street' => $addressParts[4],
                'building' => $addressParts[5],
                'room' => $addressParts[6],
            ];
        }

        if (empty($addressPartsData['subject']) && !empty($addressPartsData['region']))
        {
            $addressPartsData['subject'] = $addressPartsData['region'];
        }
        elseif (empty($addressPartsData['region']) && !empty($addressPartsData['subject']))
        {
            $addressPartsData['region'] = $addressPartsData['subject'];
        }

        return $this->normalizeAddressParts($addressPartsData);
    }

    /**
     * @param array $addressParts
     *
     * @return array
     */
    protected function normalizeAddressParts(array $addressParts): array
    {
        if (!empty($addressParts))
        {
            if (!empty($addressParts['subject']))
            {
                $addressParts['subject'] = AddressHelper::normalizeSubjectName($addressParts['subject']);
            }

            if (!empty($addressParts['region']))
            {
                $addressParts['region'] = AddressHelper::normalizeRegionName($addressParts['region']);
            }

            if (!empty($addressParts['city']))
            {
                $addressParts['city'] = AddressHelper::normalizeCityName($addressParts['city']);
            }

            if (!empty($addressParts['district']))
            {
                $addressParts['district'] = AddressHelper::normalizeDistrictName($addressParts['district']);
            }

            if (!empty($addressParts['street']))
            {
                $addressParts['street'] = AddressHelper::normalizeStreetName($addressParts['street']);
            }

            if (!empty($addressParts['building']))
            {
                $addressParts['building'] = AddressHelper::normalizeBuildingNumber($addressParts['building']);
            }

            if (!empty($addressParts['room']))
            {
                $addressParts['room'] = AddressHelper::normalizeRoomNumber($addressParts['room']);
            }
        }
        $addressParts = array_intersect_key($addressParts, [
            'region' => 1, 'subject' => 1, 'city' => 1, 'district' => 1, 'street' => 1, 'building' => 1, 'room' => 1,
        ]);

        return $addressParts;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getDistrict()
    {
        return $this->district;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getBuilding()
    {
        return $this->building;
    }

    /**
     * @return string
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * @return array
     */
    public function asArray(): array
    {
        return [
            'subject' => $this->subject,
            'region' => $this->region,
            'street' => $this->street,
            'city' => $this->city,
            'district' => $this->district,
            'building' => $this->building,
            'room' => $this->room,
        ];
    }
}
