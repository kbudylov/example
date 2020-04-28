<?php

namespace app\components\address;

/**
 * Class AddressHelper.
 */
class AddressHelper
{
    /**
     * @param string $string
     *
     * @return string
     */
    public static function normalizeCommonString(string $string): string
    {
        $string = trim($string);
        $string = mb_strtolower($string);
        $string = preg_replace([
            '/[.;:_,0-9+=]/',
        ], ' ', $string);
        $string = preg_replace([
            '/_/',
            '/ {2,}/',
        ], ' ', $string);
        $string = preg_replace([
            '/-{2,}/',
            '/\s+-\s+/',
        ], '-', $string);
        $string = preg_replace([
            '/^ ?-/',
            '/- ?$/',
        ], '', $string);
        $string = trim($string);

        return $string;
    }

    /**
     * @param string $subjectName
     *
     * @return string
     */
    public static function normalizeSubjectName($subjectName)
    {
        $subjectName = (string) $subjectName;
        $subjectName = static::normalizeCommonString($subjectName);
        $subjectName = preg_replace("/ +обл\.?$/i", ' область', $subjectName);
        $subjectName = preg_replace("/ +окр\.?$/i", ' округ', $subjectName);
        $subjectName = preg_replace("/ +кр\.?$/i", ' край', $subjectName);
        $subjectName = preg_replace("/(\s)респ(\s)/i", ' республика', $subjectName);
        $subjectName = preg_replace("/^респ(\s)/i", 'республика ', $subjectName);

        return $subjectName;
    }

    /**
     * @param string $regionName
     *
     * @return string
     */
    public static function normalizeRegionName($regionName)
    {
        $regionName = (string) $regionName;

        return static::normalizeSubjectName($regionName);
    }

    /**
     * @param string $cityName
     *
     * @return string
     */
    public static function normalizeCityName(string $cityName)
    {
        $cityName = static::normalizeCommonString($cityName);
        $cityName = preg_replace([
            "/(^|\s+)город(\s|$)/i",
            "/(^|\s+)гор(\s|$)/i",
            "/(^|\s+)г(\s|$)/i",
            "/(^|\s+)поселок(\s|$)/i",
            "/(^|\s+)посёлок(\s|$)/i",
            '/(^|\s+)пос(\s|$)/i',
            '/(^|\s)пгт(\s|$)/i',
            '/(^|\s)пос(\s|$)/i',
            '/(^|\s+)п(\s|$)/i',
            '/(^|\s+)рп(\s|$)/i',
            '/(^|\s+)деревня(\s|$)/i',
            '/(^|\s+)дер(\s|$)/i',
            '/(^|\s+)д(\s|$)/i',
        ], '', $cityName);

        return $cityName;
    }

    /**
     * @param string $districtName
     *
     * @return string
     */
    public static function normalizeDistrictName(string $districtName)
    {
        $districtName = static::normalizeCommonString($districtName);
        $districtName = preg_replace([
            '/(^|\s)р-он($|\s)/',
            '/(^|\s)район($|\s)/',
            '/(^|\s)р($|\s)/',
        ], '', $districtName);

        return $districtName;
    }

    /**
     * @param string $streetName
     *
     * @return string
     */
    public static function normalizeStreetName(string $streetName)
    {
        $streetName = static::normalizeCommonString($streetName);
        $streetName = preg_replace([
            '/(^|\s)ул($|\s)/',
            '/(^|\s)улица($|\s)/',
        ], '', $streetName);

        return $streetName;
    }

    /**
     * @param string $buildingNumber
     *
     * @return string
     */
    public static function normalizeBuildingNumber(string $buildingNumber)
    {
        $buildingNumber = trim($buildingNumber);
        $buildingNumber = mb_strtolower($buildingNumber);
        $buildingNumber = preg_replace([
            '/^\./u',
            '/дом/iu',
            '/д\./iu',
        ], '', $buildingNumber);
        $buildingNumber = preg_replace('/([а-я]+) ?д/iu', '\1', $buildingNumber);
        $buildingNumber = preg_replace([
            '/\\/ ?[а-я]+/iu',
        ], '', $buildingNumber);

        preg_match_all('/([0-9]+\\/?[0-9]{0,2}) *([абвгд]?)/ui', $buildingNumber, $match_building_number);
        preg_match_all('/(корпус|корп|кор|к)[ .]*([0-9]+[абвгдежз]?)/ui', $buildingNumber, $match_corp_number);
        preg_match_all('/(строение|стр|с)[ .]*([0-9]+[абвгдежз]?)/ui', $buildingNumber, $match_str_number);

        $b_number = $b_letter = $building = null;

        if (!empty($match_building_number[1][0]))
        {
            $b_number = $match_building_number[1][0];
            $b_number = preg_replace('/\\/ ?$/iu', '', $b_number);
        }

        if (!empty($match_building_number[2][0]))
        {
            $b_letter = $match_building_number[2][0];
        }

        if (!empty($b_number))
        {
            $building = $b_number . $b_letter;

            if (!empty($match_str_number[2][0]))
            {
                $building .= ' стр ' . $match_str_number[2][0];
            }

            if (!empty($match_corp_number[2][0]))
            {
                $building .= ' корп ' . $match_corp_number[2][0];
            }
        }
        else
        {
            return $buildingNumber;
        }

        return $building;
    }

    /**
     * @param string $roomNumber
     *
     * @return string
     */
    public static function normalizeRoomNumber(string $roomNumber)
    {
        $roomNumber = trim($roomNumber);
        $roomNumber = mb_strtolower($roomNumber);
        $roomNumber = preg_replace([
            '/^\./u',
            '/квартира/iu',
            '/кв\.?/iu',
        ], '', $roomNumber);

        preg_match('/([0-9]+[а-я]*)/iu', $roomNumber, $match_room);

        if (!empty($match_room[1]))
        {
            $roomNumber = $match_room[1];
        }
        $roomNumber = trim($roomNumber);

        return $roomNumber;
    }
}
