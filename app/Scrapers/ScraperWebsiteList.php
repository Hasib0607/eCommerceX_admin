<?php

namespace App\Scrapers;

use App\Scrapers\Website\AarongScraper;
use App\Scrapers\Website\AroggaScraper;
use App\Scrapers\Website\BestelectronicsScraper;
use App\Scrapers\Website\BishworangScraper;
use App\Scrapers\Website\CatseyeScraper;
use App\Scrapers\Website\DarazScraper;
use App\Scrapers\Website\EcstasybdScraper;
use App\Scrapers\Website\GentleparkScraper;
use App\Scrapers\Website\LerevecrazeScraper;
use App\Scrapers\Website\PickabooScraper;
use App\Scrapers\Website\RichmanbdScraper;
use App\Scrapers\Website\ShajgojScraper;
use App\Scrapers\Website\StartechScraper;
use App\Scrapers\Website\GadgetnMusicScraper;
use App\Scrapers\Website\GadstyleScraper;
use App\Scrapers\Website\ToffparkScraper;
use App\Scrapers\Website\DrmScraper;

class ScraperWebsiteList
{
    public static function get(): array
    {
        return [
            "daraz.com.bd" => [
                "class" => DarazScraper::class,
                "perPage" => 20,
            ],
            "startech.com.bd" => [
                "class" => StartechScraper::class,
                "perPage" => 40,
            ],
            "gadgetnmusic.com" => [
                "class" => GadgetnMusicScraper::class,
                "perPage" => 20,
            ],
            "gadstyle.com" => [
                "class" => GadstyleScraper::class,
                "perPage" => 30,
            ],
            "toffpark.com" => [
                "class" => ToffparkScraper::class,
                "perPage" => 8,
            ],
            "drm.com.bd" => [
                "class" => DrmScraper::class,
                "perPage" => 12,
            ],
            "shop.shajgoj.com" => [
                "class" => ShajgojScraper::class,
                "perPage" => 18,
            ],
            "arogga.com" => [
                "class" => AroggaScraper::class,
                "perPage" => 20,
            ],
            "bishworang.com.bd" => [
                "class" => BishworangScraper::class,
                "perPage" => 20,
            ],
            "pickaboo.com" => [
                "class" => PickabooScraper::class,
                "perPage" => 20,
            ],
            "bestelectronics.com.bd" => [
                "class" => BestelectronicsScraper::class,
                "perPage" => 20,
            ],
            "aarong.com" => [
                "class" => AarongScraper::class,
                "perPage" => 12,
            ],
            "richmanbd.com" => [
                "class" => RichmanbdScraper::class,
                "perPage" => 16,
            ],
            "ecstasybd.com" => [
                "class" => EcstasybdScraper::class,
                "perPage" => 12,
            ],
            "lerevecraze.com" => [
                "class" => LerevecrazeScraper::class,
                "perPage" => 24,
            ],
            "gentlepark.com" => [
                "class" => GentleparkScraper::class,
                "perPage" => 0,
            ],
            "catseye.com.bd" => [
                "class" => CatseyeScraper::class,
                "perPage" => 24,
            ],
        ];
    }

    public static function getShortList(): array
    {
        return [
            "daraz.com.bd" => [
                "class" => DarazScraper::class,
                "perPage" => 20,
            ],
//            "startech.com.bd" => [
//                "class" => StartechScraper::class,
//                "perPage" => 40,
//            ],

        ];
    }


}
