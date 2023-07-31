<?php
/**
 * @copyright   Copyright (C) 2020 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

namespace Combodo\iTop\Extension\Saml;
use IssueLog;
use MetaModel;

/**
 *  Simple logger to write to log/saml.log
 */
class Logger
{
    const ERROR = 'Error';
    const WARNING = 'Warning';
    const INFO = 'Info';
    const DEBUG = 'Debug';

    private static $bDebug = null;

    private static function Log($sLogLevel, $sMessage)
    {
        if (static::$bDebug === null)
        {
            static::$bDebug = MetaModel::GetModuleSetting('combodo-saml', 'debug', false);
        }

        if ((!static::$bDebug) && ($sLogLevel != static::ERROR))
        {
            // If not in debug mode, log only ERROR messages
            return;
        }

        $sLogFile = APPROOT.'/log/saml.log';

        $hLogFile = fopen($sLogFile, 'a');
        if ($hLogFile !== false)
        {
            flock($hLogFile, LOCK_EX);
            $sDate = date('Y-m-d H:i:s');
            fwrite($hLogFile, "$sDate | $sLogLevel | $sMessage\n");
            fflush($hLogFile);
            flock($hLogFile, LOCK_UN);
            fclose($hLogFile);
        }
        else
        {
            IssueLog::Error("Cannot open log file '$sLogFile' for writing.");
            IssueLog::Info($sMessage);
        }
    }

    public static function Error($sMessage)
    {
        static::Log(static::ERROR, $sMessage);
    }


    public static function Warning($sMessage)
    {
        static::Log(static::WARNING, $sMessage);
    }

    public static function Info($sMessage)
    {
        static::Log(static::INFO, $sMessage);
    }

    public static function Debug($sMessage)
    {
        static::Log(static::DEBUG, $sMessage);
    }
}