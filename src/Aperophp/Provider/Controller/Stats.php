<?php

namespace Aperophp\Provider\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class Stats implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->match('stats_{type}.html', function(Request $request, $type) use ($app)
        {
          $app['session']->set('menu', 'stats');
          $types = array(
            'all' => 'Toutes',
            'year' => 'Depuis un an',
            'month' => 'Depuis 6 mois',
          );

          if (!isset($types[$type])) {
            $type = 'all';
          }
          $dateFrom = null;
          $date = new \DateTime();
          if ($type == 'year') {
            $date->modify('-1 year');
            $dateFrom = $date->format('Y-m-d');
          } elseif ($type == 'month') {
            $date->modify('-6 month');
            $dateFrom = $date->format('Y-m-d');
          }

          $totalCount = $app['drinks']->getCount($dateFrom);

          $geo = array();
          foreach ($app['drinks']->getGeoInformations($dateFrom) as $info) {
            $geo[] = array($info['latitude'], $info['longitude'], $info['description']);
          }

          $displayedDate = $app['drinks']->findFirst($dateFrom);
          if (count($displayedDate)) {
            $displayedDate = $dateFrom;
          }

          return $app['twig']->render('stats/stats.html.twig', array(
            'total' => $totalCount,
            'total_participants' => $app['drinks']->countAllParticipants($dateFrom),
            'avg_participants' => $app['drinks']->averageParticipantsByCity($dateFrom),
            'date_participants' => $app['drinks']->countParticipantsByDate($dateFrom),
            'date_from' => $displayedDate,
            'geo' => $geo,
            'type' => $type,
            'types' => $types,
            ));
        })
        ->bind('_stats')
        ->method('GET');

        return $controllers;
    }
}
