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
        $query = "select g.name from `group` g join contact_group cg on cg.group = g.id where cg.contact=$uid";
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
        if ($groupId < 0) {
            return false;
        }
        $slotId = $this->getSlotId();
        if ($slotId < 0) {
            return false;
        }
        $doneId = $this->getDoneId();
        if ($doneId < 0) {
            return false;
        }
        $aloneId = $this->getAloneId();
        if ($aloneId < 0) {
            return false;
        }

        $query =
            "select distinct ".
                "c.id,".
                "concat(c.name,' ',c.surname) as fullname,".
                "c.instrument,".
                "i.name as instrumentname,".
                "cv.intval as slot,".
                "cvdone.intval as done,".
                "cvalone.intval as alone ".
            "from (contact c ".
                "join instrument i ON c.instrument=i.id ".
                "join customfield_value cv ON cv.customField=$slotId and cv.oid=c.id ".
                "join customfield_value cvdone ON cvdone.customField=$doneId and cvdone.oid=c.id ".
                "join customfield_value cvalone ON cvalone.customField=$aloneId and cvalone.oid=c.id ".
                "join contact_group cg ON c.id=cg.contact) ".
            "where cg.group=$groupId ".
            "order by instrument,fullname";
        $groupContacts = $this->database->getSelection($query);
        for ($i=1; $i<count($groupContacts); $i++) {
            $x = strrpos($groupContacts[$i]["fullname"], " ");
            if ($x > 0) {
                $groupContacts[$i]["fullname"] = substr($groupContacts[$i]["fullname"],0,$x+2).".";
            }
        }

        return $groupContacts;
    }

    function readParticipation($config) {
        $rid = $config["rehearsal"];
        if ($rid < 0) {
            return array();
        }
        $query = "select distinct user, participate from rehearsal_user where rehearsal=$rid";
        $x = $this->database->getSelection($query);
        $res = array();
        for ($i=1; $i<count($x); $i++) {
            $res[$x[$i]["user"]] = $x[$i]["participate"];
        }
        return $res;
    }

    function setMemberSlot($memberId, $newSlot) {
        $slotId = $this->getSlotId();
        if ($slotId < 0) {
            return;
        }
        $this->database->execute(
            "update customfield_value ".
            "set intval = ".$newSlot." ".
            "where oid = ".$memberId." and customfield = ".$slotId
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

    function getSlotId() {
        $slotIdTable = $this->database->getSelection("SELECT Id FROM customfield WHERE techname = 'stimmbildung_slot'");
        if (count($slotIdTable) < 2) {
            echo "Error: missing customfield stimmbildung_slot";
            return -1;
        }
        return $slotIdTable[1]["Id"];
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

    function getAloneId() {
        $aloneTable = $this->database->getSelection("SELECT Id FROM customfield WHERE techname = 'stimmbildung_alone'");
        if (count($aloneTable) < 2) {
            echo "Error: missing customfield stimmbildung_alone";
            return -1;
        }
        return $aloneTable[1]["Id"];
    }

    function readSlots()
    {
        $query = "SELECT * FROM stimmbildung_slots";
        $slots = $this->database->getSelection($query);
        return $slots;
    }

	function setSlots($oldSlots, $newSlots, $members)
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
			for ($i=1; $i<count($members); $i++) {
				$memberId = $members[$i]["id"];
				$slot = $members[$i]["slot"];
				if ($slot > 0) {
					$this->setMemberSlot($memberId, 0);
				}
			}
		}
	}

    // return array(participation, slots, slotTable, instruments, members)
    function readData($config)
    {
		$participation = $this->readParticipation($config);
        $slots = $this->readSlots();
        $members = $this->readMembers();
        $instruments = $this->getInstruments($members);

        $slotTable = array_fill(0,count($slots), array());
        for ($i=1; $i<count($members); $i++) {
            $id = $members[$i]["id"];
            $slot = $members[$i]["slot"];
            if ($slot < 0 || $slot >= count($slots)) {
                $slot = 0;
                $this->setMemberSlot($members[$i]["id"], 0);
            }

            $p = -1;
            if (array_key_exists($id, $participation)) {
                $p = $participation[$id];
            }
            $slotTable[$slot][] = array($id, "".$members[$i]["fullname"], $members[$i]["instrumentname"],
                $members[$i]["done"], $members[$i]["alone"], $p);
        }

        return array($participation, $slots, $slotTable, $instruments, $members);
    }

    function readConfig() {
        $query = "select * from stimmbildung_config";
        $x = $this->database->getSelection($query);
        $result = array("rehearsal" => -10, "autofill" => -10, "activegroup" => -10, "activegroupshown" => -10);
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
        if ($result["activegroupshown"]==-10) {
            $this->database->execute(
                "insert stimmbildung_config values(NULL,'activegroupshown',-1)"
            );
            $result["activegroupshown"]=-1;
        }
        return $result;
    }

    // return (gid, name, sgid, sname)
    function getActiveGroup($config, $instruments)
    {
		if (empty($instruments)) {
			if ($config["activegroup"] >= 0) {
				$config["activegroup"] = -1;
				$this->setActiveGroup(-1);
			}
			if ($config["activegroupshown"] >= 0) {
				$config["activegroupshown"] = -1;
				$this->setActiveGroupShown(-1);
			}
		}
        else {
			if ($config["activegroup"] < 0 or !array_key_exists($config["activegroup"], $instruments)) {
				foreach ($instruments as $key => $value) {
					if ($key > $config["activegroup"]) {
						$config["activegroup"] = $key;
						$this->setActiveGroup($key);
						break;
					}
				}
				if (!array_key_exists($config["activegroup"], $instruments)) {
					$instId = array_keys($instruments)[0];
					$config["activegroup"] = $instId;
					$this->setActiveGroup($instId);
				}
			}
			if ($config["activegroupshown"] < 0 or !array_key_exists($config["activegroupshown"], $instruments)) {
				foreach ($instruments as $key => $value) {
					if ($key > $config["activegroupshown"]) {
						$config["activegroupshown"] = $key;
						$this->setActiveGroupShown($key);
						break;
					}
				}
				if (!array_key_exists($config["activegroupshown"], $instruments)) {
					$instId = array_keys($instruments)[0];
					$config["activegroupshown"] = $instId;
					$this->setActiveGroupShown($instId);
				}
			}
		}

        return array("gid" => $config["activegroup"], "name" => $instruments[$config["activegroup"]],
            "sgid" => $config["activegroupshown"], "sname" => $instruments[$config["activegroupshown"]]);
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

    function finalizeRehearsal($config, $slots, $slotTable, $newRid, $instruments, $members) {
        $this->log("Finalize rehearsal");
		$participation = $this->readParticipation($config);

		// set 'done' to all members in slots 1..n and remove them from their slots
        $this->log("set done to all members in slots");
		$map = Array();
        for ($i=1; $i<count($slotTable); $i++) {
            for ($j=0; $j<count($slotTable[$i]); $j++) {
                $memberId = $slotTable[$i][$j][0];
                $this->setMemberDone($memberId, 1);
                $this->setMemberSlot($memberId, 0);
				$this->log("remove member $memberId from slot and mark as 'done'");
				$map[$memberId] = 1;
            }
        }
		for ($i=1; $i<count($members); $i++) {
			if (array_key_exists($members[$i]["id"], $map)) {
				$members[$i]["done"] = 1;
				$this->log("member done: ".$members[$i]["fullname"]." (".$members[$i]["id"].")");
			}
		}

        $activeGroup = $this->getActiveGroup($config, $instruments);
        $activeGid = $activeGroup["gid"];
        $activeShownGid = $activeGroup["sgid"];

        // check if all members in group gid are done -> remove all done in gid and gid++
		$nextGid = $activeGid;
        $groupDone = true;
        for ($i = 1; $i < count($members); $i++) {
            if ($members[$i]["done"]) {
                continue;
            }
            $gid = $members[$i]["instrument"];
            if ($nextGid <= $activeGid && ($gid < $nextGid || $gid > $activeGid) ||
                ($nextGid > $activeGid && ($gid > $activeGid && $gid < $nextGid))) {
                $nextGid = $gid;
            }
            if ( $gid == $activeGid ) {
                $groupDone = false;
				$this->log("group not 'done' bc of ".$members[$i]["fullname"]." (".$members[$i]["id"].")");
            }
        }
        if ($groupDone) {
            $this->log("group $activeGid is done");
            if ($activeGid == $activeShownGid) {
                $this->log("push shown group to $nextGid");
                $this->setActiveGroupShown($nextGid);
                $config["activegroupshown"] = $nextGid;
                $activeShownGid = $activeGroup["sgid"] = $nextGid;
            }
			// unmark all 'done' in gid members and shifting gid++
			for ($i = 1; $i < count($members); $i++) {
				if ($members[$i]["done"]) {
					$gid = $members[$i]["instrument"];
					if ( $nextGid <= $activeGid && ($gid < $nextGid || $gid >= $activeGid) ||
							$nextGid > $activeGid && ($gid >= $activeGid && $gid < $nextGid) ) {
                                $this->log("setMemberDone(".$members[$i]["id"].", 0)");
						$this->setMemberDone($members[$i]["id"], 0);
                        $members[$i]["done"] = 1;
					}
				}
			}
            $this->log("moveactiveGroup to $nextGid");
            $this->setActiveGroup($nextGid);
            $config["activegroup"] = $nextGid;
            $activeGid = $activeGroup["gid"] = $nextGid;
        }

		// check if all _participating_ members in group sgid are done -> sgid++
		$nextGid = $activeShownGid;
        $groupDone = true;
        for ($i = 1; $i < count($members); $i++) {
			$id = $members[$i]["id"];
            if ($members[$i]["done"] || (array_key_exists($id, $participation) && $participation[$id] == 0)) {
                continue;
            }
            $gid = $members[$i]["instrument"];
            if ($nextGid <= $activeShownGid && ($gid < $nextGid || $gid > $activeShownGid) ||
                ($nextGid > $activeShownGid && ($gid > $activeShownGid && $gid < $nextGid))) {
                $nextGid = $gid;
            }
            if ( $gid == $activeShownGid ) {
                $groupDone = false;
            }
        }
        if ($groupDone) {
            $this->log("showngroup $activeShownGid is done");
            if ($nextGid == $activeGid) {
				// unmark all 'done' in gid members and shifting gid++
				for ($i = 1; $i < count($members); $i++) {
					if ($members[$i]["done"]) {
						$gid = $members[$i]["instrument"];
						if ( $nextGid <= $activeShownGid && ($gid < $nextGid || $gid >= $activeShownGid) ||
								$nextGid > $activeShownGid && ($gid >= $activeShownGid && $gid < $nextGid) ) {
                                    $this->log("setMemberDone(".$members[$i]["id"].", 0)");
							$this->setMemberDone($members[$i]["id"], 0);
                            $members[$i]["done"] = 1;
						}
					}
				}
                $this->log("push main group to $nextGid");
                $this->setActiveGroup($nextGid);
                $config["activegroup"] = $nextGid;
                $activeGid = $activeGroup["gid"] = $nextGid;
            }
			$this->setActiveGroupShown($nextGid);
            $config["activegroupshown"] = $nextGid;
            $activeGroup["sgid"] = $activeShownGid = $nextGid;
        }

		// update rehearsal
		$this->log("update rehearsal to $newRid");
        $this->setRehearsal($newRid);
        $config["rid"] = $newRid;
		$this->log("read data from database");
        list($participation, $slots, $slotTable, $instruments, $members) = $this->readData($config);

		// do new planning
        $this->log("do new planning");
        $this->fillSlotsInternal($config, $slots, $slotTable, $instruments, $participation, $members);
    }

    function setActiveGroup($gid) {
        $query = "update stimmbildung_config ".
            "set intval = $gid where name = 'activegroup'";
        $this->database->execute($query);
    }

    function setActiveGroupShown($sgid) {
        $query = "update stimmbildung_config ".
            "set intval = $sgid where name = 'activegroupshown'";
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

    function fillSlots($config, $slots, $slotTable, $instruments, $participation, $members) {
		// remove all members from slots
        for ($i=1; $i<count($slotTable); $i++) {
            for ($j=0; $j<count($slotTable[$i]); $j++) {
                $memberId = $slotTable[$i][$j][0];
                $this->setMemberSlot($memberId, 0);
            }
        }

		// fill slots
        list($participation, $slots, $slotTable, $instruments, $members) = $this->readData($config);
        $this->fillSlotsInternal($config, $slots, $slotTable, $instruments, $participation, $members);
    }

	function log($s) {
		$ENABLE_LOG = false;
		if (!$ENABLE_LOG) {
			return;
		}

		echo($s."<br/>\n");
	}

    function fillSlotsInternal($config, $slots, $slotTable, $instruments, $participation, $members) {
        $SLOT_COUNT = 3;
		$this->log("fillSlotsInternal");

        // members: id, fullname, instrument, instrumentname, slot, done, alone
		$activeGroup = $this->getActiveGroup($config, $instruments);
        $activeGid = $activeGroup["gid"];
		$this->log("    activeGroup: ".$activeGroup["name"]." (GID $activeGid)");

        // fill all slots
        $localGroup = array();
        $localGroupCount = 0; // number of free members without tag 'alone'
		$initialActiveGid = $activeGid;
		$nextGid = $activeGid;
        $actSlot = 1;
        $slotCount = 0; // number of members in actual slot
        while ($actSlot < count($slots)) {
			$this->log("    fill actSlot=$actSlot");

            // collect all free members
            for ($i = 1; $i < count($members); $i++) {
                if ($members[$i]["done"]) {
                    continue;
                }
				$id = $members[$i]["id"];
				if (!array_key_exists($id, $participation) || $participation[$id] != 0) {
					$gid = $members[$i]["instrument"];
					if ($nextGid <= $activeGid && ($gid < $nextGid || $gid > $activeGid) ||
						($nextGid > $activeGid && ($gid > $activeGid && $gid < $nextGid))) {
						$nextGid = $gid;
					}
					if ($gid == $activeGid) {
                        $this->log("    add member to choice: $id (".$members[$i]["fullname"].")");
						array_push($localGroup, array($id, $i));
                        if (!$members[$i]["alone"]) {
                            $localGroupCount++;
                        }
					}
				}
            }
			$this->log("    localGroup=(size ".count($localGroup)."): ".implode(",",array_map(function($el){ return $el[0]; }, $localGroup)));

            // pick members from localGroup
            while (!empty($localGroup) && $actSlot<count($slots)) {
                // check for free members left
                if ($slotCount > 0 && $localGroupCount == 0) {
                    $actSlot++;
                    $slotCount = 0;
                    continue;
                }

                // draw free member
                while (true) {
                    $j = random_int(0, count($localGroup) - 1);
                    $id = $localGroup[$j][0];
                    $i = $localGroup[$j][1];
                    if ($slotCount > 0 && $members[$i]["alone"]) {
                        continue; // ends since $localGroupCount > 0 (guaranteed in previous block)
                    }
                    break;
                }
				$this->log("    pick member ".$id." for slot ".$actSlot);
                $this->setMemberSlot($id, $actSlot);
                array_splice($localGroup, $j, 1);

                // advance slot
                if ($members[$i]["alone"]) {
                    $actSlot++;
                    $slotCount = 0;
                } else {
                    $slotCount++;
                    if ($slotCount >= $SLOT_COUNT || ($slotCount >= $SLOT_COUNT-1 && $localGroupCount==4)) {
                        $localGroupCount -= $SLOT_COUNT;
                        $actSlot++;
                        $slotCount = 0;
                    }
                }
            }

            // advance group IDs
            if (empty($localGroup) && $actSlot<count($slots)) {
				$isOver = ( $activeGid < $initialActiveGid &&
						($nextGid >= $initialActiveGid || $nextGid <= $activeGid) ) ||
					($nextGid >= $initialActiveGid && $activeGid >= $nextGid);
                $activeGid = $nextGid;
                if ($slotCount > 0) {
                    $actSlot++;
                    $slotCount = 0;
                }
				$this->log("    change activeGroup to GID ".$activeGid);
				if ($isOver) {
					$this->log("    started at beginning group -> did one loop, exiting");
					break;
				}
            }
        }
    }
}
?>
