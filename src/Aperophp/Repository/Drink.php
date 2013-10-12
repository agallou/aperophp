<?php

namespace Aperophp\Repository;

use Aperophp\Repository\City;


/**
 * Drink repository
 */
class Drink extends Repository
{
    const KIND_DRINK        = 'drink';
    const KIND_CONFERENCE   = 'talk';

    public function getTableName()
    {
        return 'Drink';
    }

    public function getCount($dateFrom, $city = City::ALL)
    {
        $dateQuery = '';
        if (null !== $dateFrom) {
          $dateQuery = ' AND day > "' . $dateFrom . '"';
        }
        if ($city != City::ALL) {
          $dateQuery .= sprintf(' AND city_id = %s', $city);
        }
        $sql = sprintf('SELECT COUNT(d.id) as count
            FROM Drink d WHERE 1 = 1 %s
        ', $dateQuery);

        $row = $this->db->fetchAssoc($sql);
        return $row['count'];
    }



    /**
     * Find drinks order by day with participants
     *
     * @param integer $limit
     *
     * @return array
     */
    public function findAll($limit = null)
    {
        if (null === $limit) {
            $limit = 3;
        }

        $sql  = sprintf(
            'SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
            ORDER BY day DESC
            LIMIT %d
        ', self::getCountParticipantsQuery(), $limit);

        return $this->db->fetchAll($sql);
    }

    public function averageParticipantsByCity($dateFrom = null, $onlyRecurrentCities = false)
    {
      //TODO ajouter having seulement si on est sur la vue tous.
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
      $having = '';
      if ($onlyRecurrentCities) {
          $having = "HAVING total_drinks > 4";
      }
      $sql = sprintf(
        'SELECT CEILING(AVG((%s))) as participants_avg, COUNT(d.id) as total_drinks, c.name as name
           FROM Drink d, City c
          WHERE d.city_id = c.id %s
          GROUP BY c.id
          %s
          ORDER BY participants_avg DESC, name

      ', self::getCountParticipantsQuery(), $dateQuery, $having);
      return $this->db->fetchAll($sql);
    }

    public function countAllParticipants($dateFrom = null, $city = City::ALL)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
      if ($city != City::ALL) {
        $dateQuery .= sprintf(' AND Drink.city_id = %s', $city);
      }
      $sql = sprintf("SELECT COUNT(*) as count
                      FROM Drink_Participation, Drink
                      WHERE Drink_Participation.drink_id = Drink.id
                        AND percentage > 0 %s", $dateQuery);
      $row = $this->db->fetchAssoc($sql);
      return $row['count'];
    }

    public function countParticipantsByDate($dateFrom = null, $city = City::ALL)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
      if ($city != City::ALL) {
        $dateQuery .= sprintf(' AND Drink.city_id = %s', $city);
      }
      $sql = sprintf("SELECT COUNT(*) as count, day
                      FROM Drink_Participation, Drink
                      WHERE Drink_Participation.drink_id = Drink.id
                        AND percentage > 0 %s
                      GROUP BY day
      ", $dateQuery);

      $dates = array();
      foreach ($this->db->fetchAll($sql) as $row) {
        $dates[$row['day']] = $row['count'];
      }

      return $dates;
    }


    public function getGeoInformations($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
       $sql = sprintf('SELECT latitude, longitude, description
          FROM Drink d
          WHERE (latitude < 48.8 OR latitude > 49.9)
           AND (longitude < 2.29 OR longitude > 2.30)
           %s
          GROUP BY d.id
          ORDER BY created_at DESC
      ', $dateQuery);

      return $this->db->fetchAll($sql);
    }


    public function findFirst($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' WHERE day > "' . $dateFrom . '"';
      }

      $sql = sprintf("SELECT *  FROM Drink %s ORDER BY day, hour, created_at LIMIT 1", $dateQuery);
      return $this->db->fetchAssoc($sql);
    }

    /**
     * Find futur drinks order by day, with participants
     */
    public function findNext($limit = null)
    {
        if (null === $limit) {
            $limit = 3;
        }

        $today = new \DateTime();

        $sql  = sprintf(
            'SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
              AND d.day >= "%s"
              ORDER BY day ASC
            LIMIT %s
        ',
        self::getCountParticipantsQuery(),
        $today->format('Y-m-d') ,
        $limit);

        return $this->db->fetchAll($sql);
    }

    /**
     * Load a specific drink
     *
     * @param integer $id
     * @return array
     */
    public function find($id)
    {
        $sql  =
            sprintf('SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
              AND d.id = ?
            LIMIT 1
            ', self::getCountParticipantsQuery());

        return $this->db->fetchAssoc($sql, array((int) $id));
    }

    public function findAllKindsInAssociativeArray()
    {
        return array(
            self::KIND_DRINK      => self::KIND_DRINK,
            self::KIND_CONFERENCE => self::KIND_CONFERENCE,
        );
    }

    protected static function getCountParticipantsQuery()
    {
      return "SELECT COUNT(*) FROM Drink_Participation WHERE drink_id = d.id AND percentage > 0";
    }

}
