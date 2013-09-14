<?php

namespace Aperophp\Repository;

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

    public function getCount($dateFrom)
    {
        $dateQuery = '';
        if (null !== $dateFrom) {
          $dateQuery = ' WHERE day > "' . $dateFrom . '"';
        }

        $sql = sprintf('SELECT COUNT(d.id) as count
            FROM Drink d %s
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

    public function averageParticipantsByCity($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
      $sql = sprintf(
        'SELECT CEILING(AVG((%s))) as participants_avg, c.name as name
           FROM Drink d, City c
          WHERE d.city_id = c.id %s
       GROUP BY c.id
       ORDER BY participants_avg DESC, name
      ', self::getCountParticipantsQuery(), $dateQuery);
      return $this->db->fetchAll($sql);
    }

    public function countAllParticipants($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
      }
      $sql = sprintf("SELECT COUNT(*) as count
                      FROM Drink_Participation, Drink
                      WHERE Drink_Participation.drink_id = Drink.id
                        AND percentage > 0 %s", $dateQuery);
      $row = $this->db->fetchAssoc($sql);
      return $row['count'];
    }

    public function countParticipantsByDate($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' AND day > "' . $dateFrom . '"';
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

      $dates = array();
      $dt = new \DateTime('2010-01-01');
      for ($i=0;$i<800;$i = $i + 20) {
        $dates[$dt->format('Y-m-d')] = rand(1,30);
        $dt->modify('+20 day');
      }

      return $dates;
    }


    public function getGeoInformations($dateFrom = null)
    {
      $dateQuery = '';
      if (null !== $dateFrom) {
        $dateQuery = ' WHERE day > "' . $dateFrom . '"';
      }
       $sql = sprintf('SELECT latitude, longitude, description
          FROM Drink d %s
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
