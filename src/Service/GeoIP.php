<?php

namespace App\Service;

use Exception;

class GeoIP
{
    // Clé d'accès pour le service GeoIP (à décommenter si nécessaire)
    //const ACCESS_KEY = '016c9614be8d2163b91b7c4255beaef0';

    /**
     * Génère un pattern pour les préfixes téléphoniques
     * @param bool $close
     * @return string
     */
    public static function patternPhonePrefix(bool $close = false): string
    {
        $pattern = "(297|93|244|1264|358|355|376|971|54|374|1684|1268|61|43|994|257|32|229|226|880|359|973|1242|387|590|375|501|1441|591|55|1246|673|975|267|236|1|61|41|56|86|225|237|243|242|682|57|269|238|506|53|5999|61|1345|357|420|49|253|1767|45|1809|1829|1849|213|593|20|291|212|34|372|251|358|679|500|33|298|691|241|44|995|44|233|350|224|590|220|245|240|30|1473|299|502|594|1671|592|852|504|385|509|36|62|44|91|246|353|98|964|354|972|39|1876|44|962|81|76|77|254|996|855|686|1869|82|383|965|856|961|231|218|1758|423|94|266|370|352|371|853|590|212|377|373|261|960|52|692|389|223|356|95|382|976|1670|258|222|1664|596|230|265|60|262|264|687|227|672|234|505|683|31|47|977|674|64|968|92|507|64|51|63|680|675|48|1787|1939|850|351|595|970|689|974|262|40|7|250|966|249|221|65|500|4779|677|232|503|378|252|508|381|211|239|597|421|386|46|268|1721|248|963|1649|235|228|66|992|690|993|670|676|1868|216|90|688|886|255|256|380|598|1|998|3906698|379|1784|58|1284|1340|84|678|681|685|967|27|260|263)";
        return $close ? "/^$pattern/" : $pattern;
    }

    /**
     * Vérifie l'adresse IP via le service IP-API
     * @param string $ip_address
     * @return object|null
     * @throws Exception
     */
    public static function check(string $ip_address): ?object
    {
        $ch = curl_init('http://ip-api.com/php/' . $ip_address);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Erreur cURL : ' . curl_error($ch));
        }
        curl_close($ch);

        $data = unserialize($json);
        return is_array($data) ? (object)$data : null;
    }

    /**
     * Récupère le préfixe téléphonique d'un pays
     * @param string $country
     * @return string|null
     */
    public static function countryPrefix(string $country): ?string
    {
        $phoneCodes = [
            // Table des codes téléphoniques des pays
            'AF' => '93', 'AL' => '355', 'DZ' => '213', 'AS' => '1-684', 'CD' => '243' // etc.
        ];

        return $phoneCodes[$country] ?? null;
    }

    /**
     * Génère le pattern pour un numéro de téléphone valide
     * @return string
     */
    public static function patternPhone(): string
    {
        return self::patternPhoneStart() . "(9[976]\d|8[987530]\d|6[987]\d|5[90]\d|42\d|3[875]\d|2[98654321]\d|9[8543210]|8[6421]|6[6543210]|5[87654321]|4[987654310]|3[9643210]|2[70]|7|1)\d{4,20}$/";
    }

    /**
     * Génère le pattern pour le début d'un numéro de téléphone
     * @param bool $close
     * @return string
     */
    public static function patternPhoneStart(bool $close = false): string
    {
        $pattern = "/^(\+|00)" . self::patternPhonePrefix();
        return $close ? $pattern . "/" : $pattern;
    }

    /**
     * Récupère l'opérateur téléphonique pour un numéro donné
     * @param string $phone
     * @param string $country
     * @param bool $allOperators
     * @return string|null
     */
    public static function phoneOperator(string $phone, string $country, bool $allOperators = false): ?string
    {
        $operatorNumber = self::phoneOperatorNumber($phone, $country);
        $operators = [
            'CD' => [
                81 => 'VODACOM_DRC',
                82 => 'VODACOM_DRC',
                83 => 'VODACOM_DRC',
                84 => 'ORANGE_DRC',
                85 => 'ORANGE_DRC',
                89 => 'ORANGE_DRC',
                98 => 'AIRTEL_DRC',
                99 => 'AIRTEL_DRC',
                90 => 'AFRICELL_DRC',
            ],
        ];

        return $operators[$country][$operatorNumber] ?? null;
    }

    /**
     * Récupère le numéro d'opérateur à partir du numéro de téléphone
     * @param string $phone
     * @param string $country
     * @return int|null
     */
    private static function phoneOperatorNumber(string $phone, string $country): ?int
    {
        preg_match('/^\+?[0-9]{3}([0-9]{2})/', $phone, $matches);
        return $matches[1] ?? null;
    }
}