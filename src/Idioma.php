<?php

namespace App;

use function Symfony\Component\Translation\t;

class Idioma
{
    public const LOGIN_ATTEMPTS = 5;

    // État
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    // Format de date
    public const DATE_FORMAT = "D M d Y h:i:s \G\M\TO";


    const STATE_SUCCESS = "SUCCESS";
    const STATE_PROCESS = "PROCESS";
    const STATE_ERROR = "ERROR";

    const STATUS_ERROR = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_CREATED = 2;
    const STATUS_PROCESS = 3;
    const STATUS_FAILED = 4;
    const STATUS_WAIT= 5;
    const WAITING_TIME = '-30 seconds';

    const LEVEL_A1 = "A1";
    const LEVEL_A2 = "A2";
    const LEVEL_B1 = "B1";
    const LEVEL_B2 = "B2";
    const LEVEL_C1 = "C1";
    const LEVEL_C2 = "C2";
    const LEVEL_NATIF = "Natif";
    const MONDAY = 'monday';
    const TUESDAY = 'tuesday';
    const WEDNESDAY = 'wednesday';
    const THURSDAY = 'thursday';
    const FRIDAY = 'friday';
    const SATURDAY = 'saturday';
    const SUNDAY = 'sunday';


    public static function getStatusList(): array
    {
        return [
            self::STATUS_CREATED => self::STATUS_CREATED,
            self::STATUS_PROCESS => self::STATUS_PROCESS,
            self::STATUS_WAIT => self::STATUS_WAIT,
            self::STATUS_SUCCESS => self::STATUS_SUCCESS,
            self::STATUS_FAILED => self::STATUS_FAILED,
            self::STATUS_ERROR => self::STATUS_ERROR,
        ];
    }

    public static function getStatusListForView(): array
    {
        return [
            self::STATUS_CREATED => "Créé",
            self::STATUS_PROCESS => "En cours",
            self::STATUS_WAIT => "Attente",
            self::STATUS_SUCCESS => "Succès",
            self::STATUS_FAILED => "Échec",
            self::STATUS_ERROR => "Erreur",
        ];
    }

    public static function getStatusBadge(): array
    {
        return [
            'danger',
            'success',
            'info',
            'warning',
            'secondary',
            'primary',
        ];
    }

    public static function getLevelList(): array
    {
        return [
            self::LEVEL_A1 => self::LEVEL_A1,
            self::LEVEL_A2 => self::LEVEL_A2,
            self::LEVEL_B1 => self::LEVEL_B1,
            self::LEVEL_B2 => self::LEVEL_B2,
            self::LEVEL_C1 => self::LEVEL_C1,
            self::LEVEL_C2 => self::LEVEL_C2,
            self::LEVEL_NATIF => self::LEVEL_NATIF,
        ];
    }

    public static function getDaysList(): array
    {
        return [
            self::MONDAY => t('monday'),
            self::TUESDAY => t('tuesday'),
            self::WEDNESDAY => t('wednesday'),
            self::THURSDAY => t('thursday'),
            self::FRIDAY => t('friday'),
            self::SATURDAY => t('saturday'),
            self::SUNDAY => t('sunday'),
        ];
    }

    public static function checkInRange($start_date, $end_date, $date_from_user): bool
    {
        // Convert to timestamp
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        $user_ts = strtotime($date_from_user);

        // Check that user date is between start & end
        return (($user_ts >= $start_ts) && ($user_ts <= $end_ts));
    }

    public static function dateRange($first, $last, $step = '+1 hour', $output_format = 'Y-m-d H:i'): array
    {

        $dates = [];
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {

            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }
}
