<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TestCommand extends Command
{
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("app:test");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $actors = [
            "Jan I Brueghel" => [
                "alternative_names" => [
                    "Jan I Brueghel",
                    "Brueghel, Jan I",
                    "Jan Brueghel I"
                ],
                "birth_date" => "1568",
                "death_date" => "1625",
                "external_authorities"=> [
                    "VIAF"=> "https:\/\/viaf.org\/viaf\/100909732",
                    "RKD"=> "https:\/\/rkd.nl\/explore\/artists\/13288"
                ],
                "works"=> [
                    "https:\/\/mskgent.be\/collection\/work\/data\/1902-C"=> [
                        "image"=> "https:\/\/imagehub.mskgent.be\/iiif\/2\/public%2F328.tif",
                        "role_nl"=> "toegeschreven aan",
                        "role_en"=> "attributed to"
                    ]
                ]
            ],
            "Jan (I) Brueghel"=> [
                "alternative_names"=> [
                    "Jan (I) Brueghel",
                    "Brueghel, Jan (I)"
                ],
                "birth_date"=> "1568",
                "death_date"=> "1625-01-13",
                "works"=> [
                    "https:\/\/www.museabrugge.be\/collection\/work\/data\/0000_GRO1561_I"=> [
                        "image"=> "https:\/\/dam.museabrugge.be\/iiif\/2\/public%2F439.tif"
                    ],
                    "https:\/\/www.museabrugge.be\/collection\/work\/data\/0000_GRO4409_III"=> [],
                    "https:\/\/www.museabrugge.be\/collection\/work\/data\/0000_GRO4410_III"=> [
                        "role_nl"=> "tekenaar"
                    ]
                ]
            ]
        ];

        var_dump(array_merge_recursive($actors['Jan I Brueghel'], $actors['Jan (I) Brueghel']));
        return 0;
    }
}