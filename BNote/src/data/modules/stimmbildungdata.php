<?php

class StimmbildungData extends AbstractData
{
    private $controller;

    function __construct($dir_prefix = "")
    {
        $this->init($dir_prefix);
    }

    function setController($ctrl)
    {
        $this->controller = $ctrl;
    }

    function readIsAdmin() {
        $uid = $this->getUserId();

        // Super User or Admin
        if($this->getSysdata()->isUserSuperUser($uid) || $this->getSysdata()->isUserMemberGroup(1, $uid)) {
            return true;
        }

        // Group "Stimmbildung Admin"
		$currContact = $this->getSysdata()->getUsersContact($uid);
		$cid = $currContact["id"];
        $query = "select g.name from `group` g join contact_group cg on cg.group = g.id where cg.contact=$cid";
        $groups = $this->database->getSelection($query);
		for ($i=1; $i<count($groups); $i++) {
			if (strcmp($groups[$i]["name"], "Stimmbildung Admin") == 0) {
				return true;
			}
		}
		return false;
    }

    function getInstruments($members) {
        $instruments = array();
        for ($i=1; $i<count($members); $i++) {
            $instruments[$members[$i]["instrument"]] = $members[$i]["instrumentname"];
        }
        ksort($instruments);
        return $instruments;
    }

    // return array(id, fullname, instrument, instrumentname, slot, done, alone)
    function readMembers()
    {
        $groupId = $this->getGroupId();
        if ($groupId < 0) {  // group Stimmbildung
            return false;
        }
		$isinSbGroupId = $this->getIsinSbGroupId();
        if ($isinSbGroupId < 0) {  // custom value stimmbildung_group
            return false;
        }

        $query =
            "select distinct ".
                "c.id,".
                "concat(c.name,' ',c.surname) as fullname,".
                "c.instrument,".
                "i.name as instrumentname,".
				"cf.intval as sbgroup ".
            "from (contact c ".
                "join instrument i ON c.instrument=i.id ".
                "join contact_group cg ON c.id=cg.contact) ".
				"join customfield_value cf ON cf.customField=$isinSbGroupId and cf.oid=c.id ".
            "where cg.group=$groupId ".
            "order by instrument,fullname";
        $groupContacts = $this->database->getSelection($query);

        return $groupContacts;
    }

	function readSbGroups()
    {
        $query =
            "select * from stimmbildung_groups";
        $groupContacts = $this->database->getSelection($query);

        return $groupContacts;
	}

	function setSbGroup($sbGroup, $newSlot)
    {
        $this->database->execute(
            "update stimmbildung_groups ".
            "set slot = ".$newSlot." ".
            "where id = ".$sbGroup
        );
	}

	function addSbGroup($sbGroup, $slot)
    {
        $this->database->execute(
            "insert stimmbildung_groups values(".$sbGroup.",".$slot.")"
        );
	}

	function removeSbGroup($sbGroup)
    {
        $this->database->execute(
            "delete from stimmbildung_groups ".
            "where id = ".$sbGroup
        );
	}

    function readParticipation($config) {
        $rid = $config["rehearsal"];
        if ($rid < 0) {
            return array();
        }
        $query = "select distinct contact,participate from rehearsal_user ru JOIN user u ON ru.user = u.id where rehearsal=$rid";
        $x = $this->database->getSelection($query);
        $res = array();
        for ($i=1; $i<count($x); $i++) {
            $res[$x[$i]["contact"]] = $x[$i]["participate"];
        }
        return $res;
    }

    function setMemberSbgroup($memberId, $newSbGroup) {
        $sbGroupId = $this->getIsinSbGroupId();
        if ($sbGroupId < 0) {
            return;
        }
        $this->database->execute(
            "update customfield_value ".
            "set intval = ".$newSbGroup." ".
            "where oid = ".$memberId." and customfield = ".$sbGroupId
        );
    }

    function setMemberDone($memberId, $newDone) {
        $doneId = $this->getDoneId();
        if ($doneId < 0) {
            return;
        }
        $this->database->execute(
            "update customfield_value ".
            "set intval = ".$newDone." ".
            "where oid = ".$memberId." and customfield = ".$doneId
        );
    }

    function getGroupId() {
        $groupIdTable = $this->database->getSelection("SELECT id FROM `group` WHERE name = 'Stimmbildung'");
        if (count($groupIdTable) < 2) {
            echo "Error: missing group Stimmbildung";
            return -1;
        }
        return $groupIdTable[1]["id"];
    }

    function getIsinSbGroupId() {
        $groupIdTable = $this->database->getSelection("SELECT id FROM `customfield` WHERE techname = 'stimmbildung_group'");
        if (count($groupIdTable) < 2) {
            echo "Error: missing customfield stimmbildung_group";
            return -1;
        }
        return $groupIdTable[1]["id"];
    }

    function getDoneId() {
        $doneTable = $this->database->getSelection("SELECT Id FROM customfield WHERE techname = 'stimmbildung_done'");
        if (count($doneTable) < 2) {
            echo "Error: missing customfield stimmbildung_done";
            return -1;
        }
        return $doneTable[1]["Id"];
    }

    function readRehearsalId() {
        $rTable = $this->database->getSelection("SELECT Id FROM customfield WHERE techname = 'stimmbildung_rehearsal'");
        if (count($rTable) < 2) {
            echo "Error: missing customfield stimmbildung_rehearsal";
            return -1;
        }
        return $rTable[1]["Id"];
    }

    function readSlots()
    {
        $query = "SELECT * FROM stimmbildung_slots";
        $slots = $this->database->getSelection($query);
        return $slots;
    }

	function setSlots($oldSlots, $newSlots, $sbGroups)
	{
		$oldC = count($oldSlots);
		$newC = count($newSlots);
		$c = min($oldC, $newC);

		for ($i=1; $i<$c; $i++) {
			if (strcmp($newSlots[$i]["name"], $oldSlots[$i]["name"]) != 0) {
				$query = "UPDATE stimmbildung_slots SET name=\"".$newSlots[$i]["name"]."\" WHERE id=".$newSlots[$i]["id"];
				$this->database->execute($query);
			}
		}

		if ($oldC > $newC) {
			for ($i=$newC; $i<$oldC; $i++) {
				$query = "DELETE FROM stimmbildung_slots WHERE id=".$oldSlots[$i]["id"];
				$this->database->execute($query);
			}
		}
		else if ($newC > $oldC) {
			for ($i=$oldC; $i<$newC; $i++) {
				$query = "INSERT stimmbildung_slots VALUES(".$newSlots[$i]["id"].",\"".$newSlots[$i]["name"]."\")";
				$this->database->execute($query);
			}
		}
		if ($oldC != $newC) {
			foreach ($sbGroups as $sbg) {
				$this->setSbGroup($sbg[1], 0);
			}
		}
	}

    // return array(participation, slots, sbGroups, instruments, members)
    function readData($config)
    {
		$participation = $this->readParticipation($config);
        $slots = $this->readSlots();
        $members = $this->readMembers();
		$sbGroups_ = $this->readSbGroups();
        $sbGroups = [];
		for ($i=1; $i<count($sbGroups_); $i++) {
			$slot = $sbGroups_[$i];
			$sbGroups[$slot["id"]] = [1000000, $slot["id"], $slot["slot"], []]; // sortId, sbGroupId, slot, participants
		}
        $instruments = $this->getInstruments($members);
        for ($i=1; $i<count($members); $i++) {
            $id = $members[$i]["id"];
            $sbgroup = $members[$i]["sbgroup"];
            if ($sbgroup < 0) {
                $sbgroup = 0;
                $this->setMemberSbGroup($members[$i]["id"], 0);
            }

            $p = -1;
            if (array_key_exists($id, $participation)) {
                $p = $participation[$id];
            }

			if (!array_key_exists($sbgroup, $sbGroups)) {
				$sbGroups[$sbgroup] = [$id, $sbgroup, 0, []]; // sortId, sbGroupId, slot, participants
				$this->addSbGroup($sbgroup, 0);
			}
			else if ($id < $sbGroups[$sbgroup][0]) { // update sort key
				$sbGroups[$sbgroup][0] = $id;
			}
			array_push($sbGroups[$sbgroup][3], array($id, "".$members[$i]["fullname"], $members[$i]["instrumentname"], $p));
        }
		foreach ($sbGroups as $id => $g) {
			if ($g[0] == 1000000) {
				$this->removeSbGroup($id);
				unset($sbGroups[$id]);
			}
		}

		usort($sbGroups, function ($a, $b) {
			return $a[0] - $b[0];
		});

        return array($participation, $slots, $sbGroups, $instruments, $members);
    }

    function readConfig() {
        $query = "select * from stimmbildung_config";
        $x = $this->database->getSelection($query);
        $result = array("rehearsal" => -10, "autofill" => -10, "activegroup" => -10);
        for ($i=1; $i<count($x); $i++) {
            $result[$x[$i]["name"]] = $x[$i]["intval"];
        }
        if ($result["rehearsal"]==-10) {
            $this->database->execute(
                "insert stimmbildung_config values(NULL,'rehearsal',-1)"
            );
        }
        if ($result["autofill"]==-10) {
            $this->database->execute(
                "insert stimmbildung_config values(NULL,'autofill',0)"
            );
            $result["autofill"]=false;
        }
        if ($result["activegroup"]==-10) {
            $this->database->execute(
                "insert stimmbildung_config values(NULL,'activegroup',-1)"
            );
            $result["activegroup"]=-1;
        }
        return $result;
    }

    function readRehearsal($config) {
        $r = $config["rehearsal"];
        if ($r < 0) {
            return NULL;
        }
        $query = "SELECT r.id as id, begin, end, approve_until, conductor, r.notes, r.status, r.serie, r.location, name, street, city, zip";
        $query .= " FROM rehearsal r, location l, address a";
        $query .= " WHERE r.id=$r AND r.location = l.id AND l.address = a.id"; // AND end > NOW()";
        $query .= " ORDER BY begin ASC LIMIT 0,1";
        return $this->database->fetchRow($query, array());
    }

    function setRehearsal($rid) {
        $query = "update stimmbildung_config ".
            "set intval = $rid where name = 'rehearsal'";
        $this->database->execute($query);
    }

    function readRehearsals() {
        $id = $this->readRehearsalId();
        $query = "SELECT r.id as id, r.begin, r.end";
        $query .= " FROM rehearsal r, customfield_value c";
        $query .= " WHERE c.customField=$id and c.oid=r.id AND end > NOW()";
        $query .= " ORDER BY r.begin ASC LIMIT 0,8";
        return $this->database->getSelection($query);
    }

	function getNextGroup($sbGroups) {
		$nextGroup = [];
		foreach ($sbGroups as $sbg) {
			if ($sbg[2] > 0) {
				$nextGroup = [];
			}
			else if (count($nextGroup) == 0) {
				$nextGroup = $sbg;
			}
		}
		if (count($nextGroup) == 0) {
			if (count($sbGroups) > 0) {
				return reset($sbGroups);
			}
			return [];
		}
		return $nextGroup;
	}

    function finalizeRehearsal($config, $slots, $sbGroups, $newRid, $instruments, $members) {
        $this->log("Finalize rehearsal");
		$participation = $this->readParticipation($config);

		$nextGroup = $this->getNextGroup($sbGroups);
		for ($i=0; $i<count($sbGroups); $i++) {
			$sbg = $sbGroups[$i];
			if ($sbg[2] != 0) {
				$this->log("remove group $sbg[1] from slot $sbg[2]");
				$this->setSbGroup($sbg[1], 0);
				$sbGroups[$i][2] = 0;
			}
		}

		// fill slots
		$this->log("do new planning");
		$running = False;
		$slotInd = 1;
		for ($it=0; $it<2; $it++) {
			for ($i=0; $i<count($sbGroups); $i++) {
				$sbg = $sbGroups[$i];
				if ($sbg[1] == $nextGroup[1]) {
					$running = True;
				}
				if ($running && $sbg[2] == 0) {
					$sbGroups[$i][2] = $slotInd;
					$this->log("move group $sbg[1] to slot $slotInd");
					$this->setSbGroup($sbg[1], $slotInd);
					$slotInd = $slotInd + 1;
					if ($slotInd > count($slots)) {
						break;
					}
				}
			}
		}

		// update rehearsal
		$this->log("update rehearsal to $newRid");
        $this->setRehearsal($newRid);
        $config["rid"] = $newRid;
		$this->log("read data from database");
        list($participation, $slots, $slotTable, $instruments, $members) = $this->readData($config);
    }

    function setActiveGroup($gid) {
        $query = "update stimmbildung_config ".
            "set intval = $gid where name = 'activegroup'";
        $this->database->execute($query);
    }

	function resetActiveGroup($gid, $members) {
		$this->setActiveGroup($gid);
		$this->setActiveGroupShown($gid);
		foreach ($members as $m) { // $m = (id, fullname, instrument, instrumentname, slot, done, alone)
			if (array_key_exists("id", $m)) {
				$this->setMemberDone($m["id"], 0);
			}
		}
	}

	function log($s) {
		$ENABLE_LOG = False;
		if (!$ENABLE_LOG) {
			return;
		}

		echo($s."<br/>\n");
	}
}
?>
