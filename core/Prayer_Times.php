<?php
/**
 * ********************************************************************
 * This script is based on a C++ prayer times calculation program
 * provided by {ITL Project} at http://www.ArabEyes.org / Thamer Mahmoud
 * https://github.com/arabeyes-org/ITL/tree/master/prayertime
 * provided under the GNU Lesser General Public License
 * ********************************************************************
 *                 Converted into php by SalahHour.com
 *              All rights reserved for Amana Estates, Inc
 * ********************************************************************
 * For Support and Other information please contact SalahHour.com
 * Distrubuted By: SalahHour.com
 */

require dirname(__FILE__) . DS . 'Settings.php';
require dirname(__FILE__) . DS . 'Settings_Constants.php';

class Prayer_Times extends Settings_Constants
{
    /**
     * Variable array
     *
     * @var array
     */
    public $vars = array(
        'loc'   => array(
            'seaLevel'   => 0,
        )
    );

    /**
     * Pointer
     *
     * @var array
     */
    public $pt;

    /**
     * Settings object
     *
     * @Settings
     */
    public $settings;

    /**
     * Constructor - Set Settings
     *
     * @param Settings $settings
     */
    public function __construct(Settings $settings)
    {
        // Load defaults
        $settings->fajir_rule   = Settings_Constants::$methods[$settings->method]['fajir_rule'];
        $settings->maghrib_rule = Settings_Constants::$methods[$settings->method]['maghrib_rule'];
        $settings->isha_rule    = Settings_Constants::$methods[$settings->method]['isha_rule'];

        $this->settings = $settings;

        // Get method
        $conf = array();
        $conf['fajrAng'] = ($this->settings->fajir_rule[0] == 0) ? $this->settings->fajir_rule[1] : 0;
        $conf['fajrInv'] = ($this->settings->fajir_rule[0] == 1) ? $this->settings->fajir_rule[1] : 0;

        $conf['ishaaAng'] = ($this->settings->isha_rule[0] == 0) ? $this->settings->isha_rule[1] : 0;
        $conf['ishaaInv'] = ($this->settings->isha_rule[0] == 1) ? $this->settings->isha_rule[1] : 0;

        $conf['imsaakAng'] = 1.5;
        $conf['imsaakInv'] = 0;
        $conf['mathhab'] = $this->settings->juristic;
        $conf['nearestLat'] = $this->settings->latitude;
        $conf['extreme'] = 7;

        $this->vars['conf'] = $conf;
    }

    /**
     * Get offset from a timezone
     *
     * @param string $userTimeZone
     * @param dateTime $dateTime
     *
     * @return type
     */
    public function getOffset($userTimeZone, $dateTime = 'now')
    {
        $userDateTimeZone = new DateTimeZone($userTimeZone);
        $userDateTime     = new DateTime($dateTime, $userDateTimeZone);
        return ($userDateTimeZone->getOffset($userDateTime) / 3600);
    }

    /**
     * Range reduce hours to 0..23
     *
     * @param int $a
     * @return int
     */
    public function fixhour($a)
    {
        $a = $a - 24.0 * floor($a / 24.0);
        $a = $a < 0 ? $a + 24.0 : $a;
        return $a;
    }

    /**
     * Add a leading 0 if necessary
     *
     * @param int $num
     * @return int
     */
    public function twoDigitsFormat($num)
    {
        return ($num <10) ? '0'. $num : $num;
    }

    /**
     * Format times
     *
     * @param array $times
     * @return array
     */
    public function formatTimes($times)
    {
        if ($this->settings->time_format == 3) {
            return $times;
        }

        foreach ($times as $sub => $input) {
            // add 0.5 minutes to round
            $input = $this->fixhour($input+ 0.5/ 60);
            $hours = floor($input);
            $minutes = floor(($input- $hours)* 60);

            $suffix = '';
            if ($this->settings->time_format != 0) {
                if ($this->settings->time_format != 2) {
                    $suffix = $hours <= 11 ? ' AM' : ' PM';
                }
                $hours = $hours <= 12 ? $hours : $hours - 12;
            }

            $times[$sub] = $this->twoDigitsFormat($hours) . ':' . $this->twoDigitsFormat($minutes) . $suffix;
        }

        return $times;
    }

    /**
     * Function to get prayer times for a certain date
     *
     * @param string $date
     *
     * @return array
     */
    public function getPrayerTimes($date)
    {
        $this->getDayInfo($date);
        return $this->formatTimes($this->getPrayerTimesByDay());
    }

    /**
     * Set date information
     *
     * @return type
     */
    public function getPrayerTimesByDay()
    {
        # locally store needed variables
        $gmt = $this->getOffset($this->settings->timezone, $this->vars['date']);
        $lat = $this->settings->latitude;
        $lon = $this->settings->longitude;
        $gmt *= 15;
        $seaLevel = $this->vars['loc']['seaLevel'];
        $fajrAng = $this->vars['conf']['fajrAng'];
        $ishaaAng = $this->vars['conf']['ishaaAng'];
        $mathhab = $this->vars['conf']['mathhab'];
        $eot = $this->timeEquation($this->vars['nDay'], $this->vars['lastDay']);
        $dec = $this->sunDeclination($this->vars['nDay']);

        # First Step: Get Prayer Times results for this day of year
        # and this location. The results are NOT the actual prayer
        # times
        $th = $this->getThuhr($lon, $gmt, $eot);
        $shMg = $this->getShoMag($lat, $dec, $seaLevel);
        $fj = $this->getFajIsh($lat, $dec, $fajrAng);
        $is = $this->getFajIsh($lat, $dec, $ishaaAng);
        $ar = $this->getAssr($lat, $dec, $mathhab);

        # Second Step A: Calculate all salat times as Base-10 numbers
        # in Normal circumstances
        # Fajr
        if ($this->vars['conf']['fajrInv'] != 0) {
            $interval = $this->vars['conf']['fajrInv'] / 60.0;
            $tempPrayer[0] = $th - $shMg - $interval;
        } else if ($fj == 0) {
            $tempPrayer[0] = 0;
        } else {
            $tempPrayer[0] = $th - $fj;
        }

        $tempPrayer[1] = $th - $shMg;
        $tempPrayer[2] = $th;
        $tempPrayer[3] = $th + $ar;
        $tempPrayer[4] = $th + $shMg;

        # Ishaa
        if ($is == 0) {
            $tempPrayer[5] = 0;
        } else {
            $tempPrayer[5] = $th + $is;
            # Ishaa Interval
            if ($this->vars['conf']['ishaaInv'] != 0) {
                $interval = $this->vars['conf']['ishaaInv'] / 60.0;
                $tempPrayer[5] = $th + $shMg + $interval;
            }
        }

        # Second Step B: Calculate all salat times as Base-10 numbers
        # in Extreme Latitudes (if set)
        # Reset status of extreme switches
        for ($i = 0; $i < 6; $i++) {
            $this->pt[$i]['isExtreme'] = 0;
            $this->pt[$i]['offset'] = 0;
        }

        if ($this->vars['conf']['extreme'] != 0) {
            # Nearest Latitude (Method.nearestLat)
            if ($this->vars['conf']['extreme'] <= 3) {
                $exFj = $this->getFajIsh($this->vars['conf']['nearestLat'], $dec, $this->vars['conf']['fajrAng']);
                $exIm = $this->getFajIsh($this->vars['conf']['nearestLat'], $dec, $this->vars['conf']['imsaakAng']);
                $exIs = $this->getFajIsh($this->vars['conf']['nearestLat'], $dec, $this->vars['conf']['ishaaAng']);
                $exAr = $this->getAssr($this->vars['conf']['nearestLat'], $dec, $this->vars['conf']['mathhab']);
                $exShMg = $this->getShoMag($this->vars['conf']['nearestLat'], $dec, $this->vars['loc']['seaLevel']);

                switch ($this->vars['conf']['extreme']) {
                    case 1: # All salat Always: Nearest Latitude
                        $tempPrayer[0] = $th - $exFj;
                        $tempPrayer[1] = $th - $exShMg;
                        $tempPrayer[3] = $th + $exAr;
                        $tempPrayer[4] = $th + $exShMg;
                        $tempPrayer[5] = $th + $exIs;
                        $this->pt[0]['isExtreme'] = 1;
                        $this->pt[1]['isExtreme'] = 1;
                        $this->pt[3]['isExtreme'] = 1;
                        $this->pt[4]['isExtreme'] = 1;
                        $this->pt[5]['isExtreme'] = 1;
                        break;

                    case 2: # Fajr Ishaa Always: Nearest Latitude
                        $tempPrayer[0] = $th - $exFj;
                        $tempPrayer[5] = $th + $exIs;
                        $this->pt[0]['isExtreme'] = 1;
                        $this->pt[5]['isExtreme'] = 1;
                        break;

                    case 3: # Fajr Ishaa if invalid: Nearest Latitude
                        if ($tempPrayer[0] <= 0) {
                            $tempPrayer[0] = $th - $exFj;
                            $this->pt[0]['isExtreme'] = 1;
                        }
                        if ($tempPrayer[5] <= 0) {
                            $tempPrayer[5] = $th + $exIs;
                            $this->pt[5]['isExtreme'] = 1;
                        }
                        break;
                }
            } # End: Nearest latitude
            # Nearest Good Day
            if (($this->vars['conf']['extreme'] > 3) && ($this->vars['conf']['extreme'] <= 5)) {
                $nGoodDay = 0;

                # Start by getting last or next nearest Good Day
                for ($i = 0; $i <= $this->vars['lastDay']; $i++) {
                    # last closest day
                    $nGoodDay = $this->vars['nDay'] - $i;
                    $exeot = $this->timeEquation($nGoodDay, $this->vars['lastDay']);
                    $exdec = $this->sunDeclination($nGoodDay);
                    $exTh = $this->getThuhr($lon, $gmt, $exeot);
                    $exFj = $this->getFajIsh($lat, $exdec, $this->vars['conf']['fajrAng']);
                    $exIs = $this->getFajIsh($lat, $exdec, $this->vars['conf']['ishaaAng']);

                    if (($exFj > 0) && ($exIs > 0))
                        break;# loop
                    # Next closest day
                    $nGoodDay = $this->vars['nDay'] + $i;
                    $exdec = $this->sunDeclination($nGoodDay);
                    $exTh = $this->getThuhr($lon, $gmt, $exeot);
                    $exFj = $this->getFajIsh($lat, $exdec, $this->vars['conf']['fajrAng']);
                    $exIs = $this->getFajIsh($lat, $exdec, $this->vars['conf']['ishaaAng']);

                    if (($exFj > 0) && ($exIs > 0))
                        break;
                }

                # Get equation results for that day
                $exeot = $this->timeEquation($nGoodDay, $this->vars['lastDay']);
                $exdec = $this->sunDeclination($nGoodDay);
                $exTh = $this->getThuhr($lon, $gmt, $exeot);
                $exFj = $this->getFajIsh($lat, $exdec, $this->vars['conf']['fajrAng']);
                $exIs = $this->getFajIsh($lat, $exdec, $this->vars['conf']['ishaaAng']);
                $exShMg = $this->getShoMag($lat, $exdec, $this->vars['loc']['seaLevel']);
                $exAr = $this->getAssr($lat, $exdec, $this->vars['conf']['mathhab']);

                switch ($this->vars['conf']['extreme']) {
                    case 4: # All salat Always: Nearest Day
                        $tempPrayer[0] = $exTh - $exFj;
                        $tempPrayer[1] = $exTh - $exShMg;
                        $tempPrayer[2] = $exTh;
                        $tempPrayer[3] = $exTh + $exAr;
                        $tempPrayer[4] = $exTh + $exShMg;
                        $tempPrayer[5] = $exTh + $exIs;
                        for ($i = 0; $i < 6; $i++)
                            $this->pt[$i]['isExtreme'] = 1;
                        break;
                    case 5: # Fajr Ishaa if invalid:: Nearest Day
                        if ($tempPrayer[0] <= 0) {
                            $tempPrayer[0] = $exTh - $exFj;
                            $this->pt[0]['isExtreme'] = 1;
                        }
                        if ($tempPrayer[5] <= 0) {
                            $tempPrayer[5] = $exTh + $exIs;
                            $this->pt[5]['isExtreme'] = 1;
                        }
                        break;
                } # end switch
            } # end nearest day
            # 1/7th of Night
            if ($this->vars['conf']['extreme'] == 6 || $this->vars['conf']['extreme'] == 7) {
                $allInterval = 24 - ($th - $shMg);
                $allInterval = $allInterval + (12 - ($th + $shMg));

                switch ($this->vars['conf']['extreme']) {
                    case 6: # Fajr Ishaa Always: 1/7th of Night
                        $tempPrayer[0] = ($th - $shMg) - ((1 / 7.0) * $allInterval);
                        $this->pt[0]['isExtreme'] = 1;
                        $tempPrayer[5] = ((1 / 7.0) * $allInterval) + ($th + $shMg);
                        $this->pt[5]['isExtreme'] = 1;
                        break;

                    case 7: # Fajr Ishaa if invalid: 1/7th of Night
                        if ($tempPrayer[0] <= 0) {
                            $tempPrayer[0] = ($th - $shMg) - ((1 / 7.0) * $allInterval);
                            $this->pt[0]['isExtreme'] = 1;
                        }
                        if ($tempPrayer[5] <= 0) {
                            $tempPrayer[5] = ((1 / 7.0) * $allInterval) + ($th + $shMg);
                            $this->pt[5]['isExtreme'] = 1;
                        }
                        break;
                }
            } # end 1/7th of Night
            # n Minutes from Shorooq Maghrib
            if ($this->vars['conf']['extreme'] == 8 || $this->vars['conf']['extreme'] == 9) {
                switch ($this->vars['conf']['extreme']) {
                    case 8: # Minutes from Shorooq/Maghrib Always
                        $tempPrayer[0] = $th - $shMg;
                        $this->pt[0]['isExtreme'] = 1;
                        $tempPrayer[5] = $th + $shMg;
                        $this->pt[5]['isExtreme'] = 1;
                        break;

                    case 9: # Minutes from Shorooq/Maghrib if invalid
                        if ($tempPrayer[0] <= 0) {
                            $tempPrayer[0] = $th - $shMg;
                            $this->pt[0]['isExtreme'] = 1;
                        }
                        if ($tempPrayer[0] <= 0) {
                            $tempPrayer[5] = $th + $shMg;
                            $this->pt[5]['isExtreme'] = 1;
                        }
                        break;
                } # end switch
            } # end n Minutes
        } # End Extreme
        # Third and Final Step: Fill the Prayer array and do decimal
        # to minutes conversion
        for ($i = 0; $i < 6; $i++) {
            $tempPrayer[$i] += ($this->pt[$i]['offset'] / 60.0);
            #$this->base6hm(tempPrayer[i], &pt[i].hour, &pt[i].minute, loc->dst);
            $this->base6hm($tempPrayer[$i], $this->getOffset($this->settings->timezone, $this->vars['date']));
            $this->pt[$i]['hour'] = $this->vars['temp']['hour'];
            $this->pt[$i]['minute'] = $this->vars['temp']['minute'];
        }
        $this->vars['pt'] = $this->pt;

        return $tempPrayer;
    }

    /**
     * Get thur times
     *
     * @param double $lon
     * @param double $ref
     * @param double $eot
     * @return double
     */
    public function getThuhr($lon, $ref, $eot)
    {
        return 12 + ($ref - $lon) / 15.0 - ($eot / 60.0) + 1 / 60;
        #return 12 + ($ref-$lon)/15.04107 - ($eot/60.0) + 1/60;
    }

    /**
     * Get Sharooq
     *
     * @param double $lat
     * @param double $dec
     * @param double $sealevel
     * @return double
     */
    public function getShoMag($lat, $dec, $sealevel)
    {
        $part1 = sin($this->deg2Rad2($lat)) * sin($dec);
        $part2 = (sin($this->deg2Rad2(-0.8333 - (0.0347 * sqrt($sealevel)))) - $part1);
        $part3 = cos($this->deg2Rad2($lat)) * cos($dec);

        #return 0.0667 * (acos($part2 / $part3) / $this->deg2Rad2(0));
        return ((acos($part2 / $part3) / (M_PI / 180.0)) / 15);
    }

    /**
     * Get Fajir
     *
     * @param double $lat
     * @param double $dec
     * @param double $ang
     * @return double
     */
    public function getFajIsh($lat, $dec, $ang)
    {
        $part1 = cos($this->deg2Rad2($lat)) * cos($dec);
        $part2 = -sin($this->deg2Rad2($ang)) - sin($this->deg2Rad2($lat)) * sin($dec);

        $test = $part2 / $part1;
        if ($test <= -1) {
            return 0;
        }

        #return 0.0667 * (acos($part2 / $part1) / $this->deg2Rad2(0));
        return (acos($part2 / $part1) / (M_PI / 180.0)) / 15;
    }

    /**
     * Get Asr
     *
     * @param double $lat
     * @param double $dec
     * @param double $mathhab
     * @return double
     */
    public function getAssr($lat, $dec, $mathhab)
    {
        $part1 = $mathhab + tan($this->deg2Rad2($lat) - $dec);
        if ($lat < 0)
            $part1 = $mathhab - tan($this->deg2Rad2($lat) - $dec);

        $part2 = (M_PI / 2.0) - atan($part1);
        $part3 = sin($part2) - sin($this->deg2Rad2($lat)) * sin($dec);
        $part4 = ($part3 / (cos($this->deg2Rad2($lat)) * cos($dec)));

        return 0.0667 * acos($part4) / $this->deg2Rad2(0);
    }

    /**
     * Time Equation
     *
     * @param double $nDay
     *
     * @return double
     */
    public function timeEquation($nDay)
    {
        #$t = 360 * ($nDay-81.0) / $lastday; # simple but sometimes inaccurate Formula
        #$eot = 9.87 * sin(2*$t) - 7.53 * cos($t) - 1.5 * sin($t);
        $t = 360 * ($nDay - 80) / 365.25;
        $t = $this->deg2Rad2($t);
        $EARTH_AVERAGE_ANGLE = .98565327;
        $eot = ($EARTH_AVERAGE_ANGLE * 10) * sin(2 * $t) - 7.53 * cos($t) - 1.5 * sin($t);
        return $eot;
    }

    /**
     * Sun Declination
     *
     * @param double $nDay
     * @return double
     */
    public function sunDeclination($nDay)
    {
        $a = 0.967 * tan(0.0086 * ($nDay - 186));
        $ELLIPSE_SHAPE = 0.016713;
        $b = ((360 / M_PI) * $ELLIPSE_SHAPE / 10.0) + 0.023 + (2 * atan($a));
        $MINUTES_PER_DEGREE = 3.98892;
        $dec = asin(cos($b) * ($MINUTES_PER_DEGREE / 10.0));

        #$dec = $this->deg2Rad2(23.45) * sin($this->deg2Rad2(.9836 * (284 + $nDay))); # simple but inaccurate formula
        return $dec;
    }

    /**
     * Convert Degrees to Radians (and vice-versa if n==0)
     *
     * @param double $n
     *
     * @return double
     */
    public function deg2Rad2($n)
    {
        if ($n == 0) {
            return (M_PI / 180.0);
        } else {
            return $n * (M_PI / 180.0);
        }
    }

    /**
     * Get days in a year
     *
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return array
     */
    public function getDayofYear($year, $month, $day)
    {
        $isLeap = (($year & 3) == 0) && (($year % 100) != 0 || ($year % 400) == 0);

        $dayList = Array(
            Array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31),
            Array(0, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)
        );

        for ($i = 1; $i < $month; $i++) {
            $day += $dayList[$isLeap][$i];
        }

        return $day;
    }

    /**
     * DMS to Decimal
     * @param double $deg
     * @param double $min
     * @param double $sec
     * @param double $dir
     *
     * @return double
     */
    public function dms2Decimal($deg, $min, $sec, $dir)
    {
        $sum = $deg + (($min / 60.0) + ($sec / 3600.0));
        if ($dir == 'S' || $dir == 'W' || $dir == 's' || $dir == 'w') {
            return $sum * (-1.0);
        }
        return $sum;
    }

    /**
     * modf implementation
     *
     * @param double $number
     * @param double $result
     */
    public function modf($number, &$result)
    {
        $whole = floor($number);
        $result = $number - $whole;
    }

    /**
     * Decimal to DMS
     *
     * @param double $decimal
     */
    public function decimal2Dms($decimal)
    {
        $tempmin = $this->modf($decimal, $n1) * 60.0;
        $tempsec = $this->modf($tempmin, $n2) * 60.0;

        # Rounding seconds
        if ($n1 < 0) {
            $n3 = ceil($tempsec);
        } else {
            $n3 = floor($tempsec);
        }

        $test = $tempsec - $n3;

        if ($test > 0.5) {
            $n3++;
        }

        if ($test < 0 && $test < -0.5) {
            $n3--;
        }

        if ($n3 == 60) {
            $n3 = 0;
            $n2++;
        }

        if ($n3 == -60) {
            $n3 = 0;
            $n2--;
        }

        $this->vars['temp']['deg'] = $n1;
        $this->vars['temp']['min'] = $n2;
        $this->vars['temp']['sec'] = $n3;
    }

    /**
     * Get days info
     *
     * @param array $date
     */
    public function getDayInfo($date)
    {
        $d = $this->getDayofYear($date['year'], $date['month'], $date['day']);
        $ld = $this->getDayofYear($date['year'], 12, 31);
        $nd = $d + 1;
        if ($nd > $ld) {
            $nd = 1;
        }
        $this->vars['nDay'] = $d;
        $this->vars['lastDay'] = $ld;
        $this->vars['nextDay'] = $nd;
        $this->vars['date'] = $date['year'] . '-' . $date['month'] . '-' . $date['day'];
    }

    /**
     * Get base6hm
     *
     * @param int $sTime
     * @param int $dst
     */
    public function base6hm($sTime, $dst)
    {
        $temp = ($sTime - floor($sTime)) * 60;
        if ($sTime < 0) {
            while ($sTime < 0) {
                $sTime = 24 + $sTime;
            }
        }

        $sTime += $dst;
        if ($sTime > 23) {
            $sTime = $this->fmodAddOn($sTime, 24);
        }

        $this->vars['temp']['hour'] = intval($sTime);
        $this->vars['temp']['minute'] = intval($temp);
    }

    /**
     * Mod Addon
     *
     * @param int $x
     * @param int $y
     *
     * @return int
     */
    public function fmodAddOn($x, $y)
    {
        $i = floor($x / $y);
        return $x - $i * $y;
    }
}