<?php

function loadDorType($options)
{
    $selQry = "SELECT DorTypeId, DorTypeName FROM dbo.AtoDorType";
    $res = execQuery(1, 1, $selQry);

    $options = array();
    if ($res !== false) {
        foreach ($res as $row) {
            $options[$row['DorTypeId']] = $row['DorTypeName'];
        }
        return $options;
    }
}

function loadLeader($options)
{
    $selQry = "SELECT OperatorId, EmployeeName FROM dbo.GenOperator WHERE ProcessId = 3 AND IsLeader = 1 AND IsActive = 1";
    $res = execQuery(1, 1, $selQry);

    $options = array();
    if ($res !== false) {
        foreach ($res as $row) {
            $options[$row['LeaderId']] = $row['EmployeeName'];
        }
        return $options;
    }
}

function isValidLine($lineNumber)
{
    $selQry = "SELECT COUNT(LineId) AS Count FROM dbo.AtoLine WHERE IsLoggedIn = 0 AND LineNumber = ?";
    $prm = array($lineNumber);
    $res = execQuery(1, 1, $selQry, $prm);

    $count = "";
    if (!empty($res)) {
        foreach ($res as $row) {
            $count = $row['Count'];
        }

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function isValidModel($modelName)
{
    $selQry = "SELECT COUNT(MODEL_ID) AS Count FROM dbo.GenModel WHERE ISACTIVE = 1 AND ITEM_ID = ?";
    $prm = array($modelName);
    $res = execQuery(1, 1, $selQry, $prm);

    $count = "";
    if (!empty($res)) {
        foreach ($res as $row) {
            $count = $row['Count'];
        }

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function isExistDor($date, $shiftId, $lineId, $modelId, $dortypeId)
{
    $selSp = "EXEC CntAtoDOR @CreatedDate=?, @ShiftId=?, @LineId=?, @ModelId=?, @DorTypeId=?";
    $prm = array($date, $shiftId, $lineId, $modelId, $dortypeId);
    $res = execQuery(1, 2, $selSp, $prm);

    $count = "";
    if (!empty($res)) {
        foreach ($res as $row) {
            $count = $row['Count'];
        }

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
