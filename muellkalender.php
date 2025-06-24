<?
class Muellabfuhr extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
		$this->RegisterPropertyString("Muell1Name", "Restmüll");
		$this->RegisterPropertyString("Muell2Name", "Papiermüll");
		$this->RegisterPropertyString("Muell3Name", "Biomüll");
		$this->RegisterPropertyString("Muell4Name", "Recyclingmüll");
		$this->RegisterPropertyString("Muell5Name", "Sondermüll");
		$this->RegisterPropertyString("Muell6Name", "Sperrmüll");
		$this->RegisterPropertyString("Muell1Art", "schwarze Tonne");
		$this->RegisterPropertyString("Muell2Art", "blaue Tonne");
		$this->RegisterPropertyString("Muell3Art", "braune Tonne");
		$this->RegisterPropertyString("Muell4Art", "gelbe Sack");
		$this->RegisterPropertyString("Muell5Art", "Sondermüll");
		$this->RegisterPropertyString("Muell6Art", "Sperrmüll");
		$this->RegisterPropertyString("Muell1Termine", "");
		$this->RegisterPropertyString("Muell2Termine", "");
		$this->RegisterPropertyString("Muell3Termine", "");
		$this->RegisterPropertyString("Muell4Termine", "");
		$this->RegisterPropertyString("Muell5Termine", "");
		$this->RegisterPropertyString("Muell6Termine", "");
		$this->RegisterPropertyBoolean("VergangeneTermineLoeschenCB", true);
		$this->RegisterPropertyString("UhrzeitBenachrHeute1", "06:00");
		$this->RegisterPropertyString("UhrzeitBenachrHeute2", "");
		$this->RegisterPropertyString("UhrzeitBenachrMorgen1", "12:00");
		$this->RegisterPropertyString("UhrzeitBenachrMorgen2", "20:00");
		$this->RegisterPropertyBoolean("Muell1Benachrichtigung", false);
		$this->RegisterPropertyBoolean("Muell2Benachrichtigung", false);
		$this->RegisterPropertyBoolean("Muell3Benachrichtigung", false);
		$this->RegisterPropertyBoolean("Muell4Benachrichtigung", false);
		$this->RegisterPropertyBoolean("Muell5Benachrichtigung", false);
		$this->RegisterPropertyBoolean("Muell6Benachrichtigung", false);
		$this->RegisterPropertyBoolean("MuellBenachrichtigungCBOX", false);
		$this->RegisterPropertyString("MuellBenachrichtigungTEXTheute", "Heute wird der §MUELLNAME abgeholt!");
		$this->RegisterPropertyString("MuellBenachrichtigungTEXTmorgen", "Morgen wird der §MUELLNAME abgeholt!");
		$this->RegisterPropertyString("MuellBenachrichtigungTEXTinTagen", "Der §MUELLNAME wird in §MUELLINTAGEN Tagen abgeholt!");
        $this->RegisterPropertyInteger("BenachrichtigungsVar", 0);
        $this->RegisterPropertyInteger("WebFrontInstanceID", 0);
        $this->RegisterPropertyInteger("SmtpInstanceID", 0);
        $this->RegisterPropertyInteger("EigenesSkriptID", 0);
        $this->RegisterPropertyBoolean("PushMsgAktiv", false);
        $this->RegisterPropertyBoolean("EMailMsgAktiv", false);
        $this->RegisterPropertyBoolean("EigenesSkriptAktiv", false);

        // NEUE EIGENSCHAFTEN FÜR ICS-IMPORT-VORSCHLÄGE
        $this->RegisterPropertyString("UnrecognizedIcsSummaries", "[]"); // Speichert unbekannte SUMMARYs als JSON-Array
        $this->RegisterPropertyString("LastImportedIcsFilePath", "");   // Speichert den Pfad der zuletzt importierten ICS-Datei
    }

    public function Destroy()
    {
        $this->UnregisterTimer("MUELL_UpdateTimer");
        
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        
        //Variablenprofil erstellen
        $this->RegisterProfileBooleanEx("Muell.NeinJa", "Recycling", "", "", Array(
                                             Array(false, "Nein",  "Recycling", 0x00FF00),
                                             Array(true, "Ja",  "Warning", 0xFF0000)
        ));
		$this->RegisterProfileInteger("Muell.Tag", "Calendar", "", " Tag",  "0", "600", 1);
		$this->RegisterProfileInteger("Muell.Tagen", "Calendar", "", " Tagen",  "0", "600", 1);
        
        //Fehlerhafte Konfiguration melden
		if (($this->ReadPropertyString("Muell1Termine") != "") AND (($this->ReadPropertyString("Muell1Name") == "") OR ($this->ReadPropertyString("Muell1Art") == "")))
		{
			$this->SetStatus(221);
			return;
		}
		if (($this->ReadPropertyString("Muell2Termine") != "") AND (($this->ReadPropertyString("Muell2Name") == "") OR ($this->ReadPropertyString("Muell2Art") == "")))
		{
			$this->SetStatus(222);
			return;
		}
		if (($this->ReadPropertyString("Muell3Termine") != "") AND (($this->ReadPropertyString("Muell3Name") == "") OR ($this->ReadPropertyString("Muell3Art") == "")))
		{
			$this->SetStatus(223);
			return;
		}
		if (($this->ReadPropertyString("Muell4Termine") != "") AND (($this->ReadPropertyString("Muell4Name") == "") OR ($this->ReadPropertyString("Muell4Art") == "")))
		{
			$this->SetStatus(224);
			return;
		}
		if (($this->ReadPropertyString("Muell5Termine") != "") AND (($this->ReadPropertyString("Muell5Name") == "") OR ($this->ReadPropertyString("Muell5Art") == "")))
		{
			$this->SetStatus(225);
			return;
		}
		if (($this->ReadPropertyString("Muell6Termine") != "") AND (($this->ReadPropertyString("Muell6Name") == "") OR ($this->ReadPropertyString("Muell6Art") == "")))
		{
			$this->SetStatus(226);
			return;
		}
		
      	if (($this->ReadPropertyBoolean("PushMsgAktiv") === true) AND ($this->ReadPropertyInteger("WebFrontInstanceID") == ""))
        {
			$this->SetStatus(201);
			return;
      	}
      	elseif (($this->ReadPropertyBoolean("EMailMsgAktiv") === true) AND ($this->ReadPropertyInteger("SmtpInstanceID") == ""))
        {
			$this->SetStatus(202);
			return;
      	}
      	elseif (($this->ReadPropertyBoolean("EigenesSkriptAktiv") === true) AND ($this->ReadPropertyInteger("EigenesSkriptID") == ""))
        {
			$this->SetStatus(203);
			return;
      	}
      	elseif ((($this->ReadPropertyBoolean("PushMsgAktiv") === false) AND ($this->ReadPropertyBoolean("EMailMsgAktiv") === false) AND ($this->ReadPropertyBoolean("EigenesSkriptAktiv") === false)) AND (($this->ReadPropertyBoolean("MuellBenachrichtigungCBOX") === true)))
      	{
			$this->SetStatus(204);
			return;
      	}
      	else
      	{
			if ($this->MuellterminePruefen() === false)
			{
				$this->SetStatus(205);
				return;
			}
			$this->SetStatus(102);
      	}
        
        //Variablen anlegen
		if (($this->ReadPropertyString("Muell1Name") != "") AND ($this->ReadPropertyString("Muell1Art") != ""))
		{
			$this->RegisterVariableBoolean("Muell1LeerungHeute", $this->ReadPropertyString("Muell1Name")." - Leerung Heute", "Muell.NeinJa", 11);
			$this->RegisterVariableBoolean("Muell1LeerungMorgen", $this->ReadPropertyString("Muell1Name")." - Leerung Morgen", "Muell.NeinJa", 11);
			$this->RegisterVariableInteger("Muell1LeerungInTagen", $this->ReadPropertyString("Muell1Name")." - Nächste Leerung in", "", 11);
			$this->RegisterVariableString("Muell1LeerungNaechsteAm", $this->ReadPropertyString("Muell1Name")." - Nächste Leerung am", "", 11);
			$this->RegisterVariableString("Muell1Termine", $this->ReadPropertyString("Muell1Name")." - Termine", "~TextBox", 11);
			IPS_SetIcon($this->GetIDForIdent("Muell1LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell1LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell1Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell1LeerungHeute"), $this->ReadPropertyString("Muell1Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell1LeerungMorgen"), $this->ReadPropertyString("Muell1Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell1LeerungInTagen"), $this->ReadPropertyString("Muell1Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell1LeerungNaechsteAm"), $this->ReadPropertyString("Muell1Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell1Termine"), $this->ReadPropertyString("Muell1Name")." - Termine");
			
			$this->EnableAction("Muell1Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell1LeerungHeute");
			$this->UnregisterVariable("Muell1LeerungMorgen");
			$this->UnregisterVariable("Muell1LeerungInTagen");
			$this->UnregisterVariable("Muell1LeerungNaechsteAm");
			$this->UnregisterVariable("Muell1Termine");
		}
		
		if (($this->ReadPropertyString("Muell2Name") != "") AND ($this->ReadPropertyString("Muell2Art") != ""))
		{		
			$this->RegisterVariableBoolean("Muell2LeerungHeute", $this->ReadPropertyString("Muell2Name")." - Leerung Heute", "Muell.NeinJa", 12);
			$this->RegisterVariableBoolean("Muell2LeerungMorgen", $this->ReadPropertyString("Muell2Name")." - Leerung Morgen", "Muell.NeinJa", 12);
			$this->RegisterVariableString("Muell2LeerungNaechsteAm", $this->ReadPropertyString("Muell2Name")." - Nächste Leerung am", "", 12);
			$this->RegisterVariableInteger("Muell2LeerungInTagen", $this->ReadPropertyString("Muell2Name")." - Nächste Leerung in", "", 12);
			$this->RegisterVariableString("Muell2Termine", $this->ReadPropertyString("Muell2Name")." - Termine", "~TextBox", 12);
			IPS_SetIcon($this->GetIDForIdent("Muell2LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell2LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell2Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell2LeerungHeute"), $this->ReadPropertyString("Muell2Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell2LeerungMorgen"), $this->ReadPropertyString("Muell2Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell2LeerungInTagen"), $this->ReadPropertyString("Muell2Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell2LeerungNaechsteAm"), $this->ReadPropertyString("Muell2Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell2Termine"), $this->ReadPropertyString("Muell2Name")." - Termine");
			
			$this->EnableAction("Muell2Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell2LeerungHeute");
			$this->UnregisterVariable("Muell2LeerungMorgen");
			$this->UnregisterVariable("Muell2LeerungInTagen");
			$this->UnregisterVariable("Muell2LeerungNaechsteAm");
			$this->UnregisterVariable("Muell2Termine");
		}
		
		if (($this->ReadPropertyString("Muell3Name") != "") AND ($this->ReadPropertyString("Muell3Art") != ""))
		{
			$this->RegisterVariableBoolean("Muell3LeerungHeute", $this->ReadPropertyString("Muell3Name")." - Leerung Heute", "Muell.NeinJa", 13);
			$this->RegisterVariableBoolean("Muell3LeerungMorgen", $this->ReadPropertyString("Muell3Name")." - Leerung Morgen", "Muell.NeinJa", 13);
			$this->RegisterVariableString("Muell3LeerungNaechsteAm", $this->ReadPropertyString("Muell3Name")." - Nächste Leerung am", "", 13);
			$this->RegisterVariableInteger("Muell3LeerungInTagen", $this->ReadPropertyString("Muell3Name")." - Nächste Leerung in", "", 13);
			$this->RegisterVariableString("Muell3Termine", $this->ReadPropertyString("Muell3Name")." - Termine", "~TextBox", 13);
			IPS_SetIcon($this->GetIDForIdent("Muell3LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell3LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell3Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell3LeerungHeute"), $this->ReadPropertyString("Muell3Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell3LeerungMorgen"), $this->ReadPropertyString("Muell3Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell3LeerungInTagen"), $this->ReadPropertyString("Muell3Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell3LeerungNaechsteAm"), $this->ReadPropertyString("Muell3Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell3Termine"), $this->ReadPropertyString("Muell3Name")." - Termine");
			
			$this->EnableAction("Muell3Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell3LeerungHeute");
			$this->UnregisterVariable("Muell3LeerungMorgen");
			$this->UnregisterVariable("Muell3LeerungInTagen");
			$this->UnregisterVariable("Muell3LeerungNaechsteAm");
			$this->UnregisterVariable("Muell3Termine");
		}
		
		if (($this->ReadPropertyString("Muell4Name") != "") AND ($this->ReadPropertyString("Muell4Art") != ""))
		{
			$this->RegisterVariableBoolean("Muell4LeerungHeute", $this->ReadPropertyString("Muell4Name")." - Leerung Heute", "Muell.NeinJa", 14);
			$this->RegisterVariableBoolean("Muell4LeerungMorgen", $this->ReadPropertyString("Muell4Name")." - Leerung Morgen", "Muell.NeinJa", 14);
			$this->RegisterVariableString("Muell4LeerungNaechsteAm", $this->ReadPropertyString("Muell4Name")." - Nächste Leerung am", "", 14);
			$this->RegisterVariableInteger("Muell4LeerungInTagen", $this->ReadPropertyString("Muell4Name")." - Nächste Leerung in", "", 14);
			$this->RegisterVariableString("Muell4Termine", $this->ReadPropertyString("Muell4Name")." - Termine", "~TextBox", 14);
			IPS_SetIcon($this->GetIDForIdent("Muell4LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell4LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell4Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell4LeerungHeute"), $this->ReadPropertyString("Muell4Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell4LeerungMorgen"), $this->ReadPropertyString("Muell4Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell4LeerungInTagen"), $this->ReadPropertyString("Muell4Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell4LeerungNaechsteAm"), $this->ReadPropertyString("Muell4Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell4Termine"), $this->ReadPropertyString("Muell4Name")." - Termine");
			
			$this->EnableAction("Muell4Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell4LeerungHeute");
			$this->UnregisterVariable("Muell4LeerungMorgen");
			$this->UnregisterVariable("Muell4LeerungInTagen");
			$this->UnregisterVariable("Muell4LeerungNaechsteAm");
			$this->UnregisterVariable("Muell4Termine");
		}
		
		if (($this->ReadPropertyString("Muell5Name") != "") AND ($this->ReadPropertyString("Muell5Art") != ""))
		{
			$this->RegisterVariableBoolean("Muell5LeerungHeute", $this->ReadPropertyString("Muell5Name")." - Leerung Heute", "Muell.NeinJa", 15);
			$this->RegisterVariableBoolean("Muell5LeerungMorgen", $this->ReadPropertyString("Muell5Name")." - Leerung Morgen", "Muell.NeinJa", 15);
			$this->RegisterVariableString("Muell5LeerungNaechsteAm", $this->ReadPropertyString("Muell5Name")." - Nächste Leerung am", "", 15);
			$this->RegisterVariableInteger("Muell5LeerungInTagen", $this->ReadPropertyString("Muell5Name")." - Nächste Leerung in", "", 15);
			$this->RegisterVariableString("Muell5Termine", $this->ReadPropertyString("Muell5Name")." - Termine", "~TextBox", 15);
			IPS_SetIcon($this->GetIDForIdent("Muell5LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell5LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell5Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell5LeerungHeute"), $this->ReadPropertyString("Muell5Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell5LeerungMorgen"), $this->ReadPropertyString("Muell5Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell5LeerungInTagen"), $this->ReadPropertyString("Muell5Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell5LeerungNaechsteAm"), $this->ReadPropertyString("Muell5Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell5Termine"), $this->ReadPropertyString("Muell5Name")." - Termine");
			
			$this->EnableAction("Muell5Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell5LeerungHeute");
			$this->UnregisterVariable("Muell5LeerungMorgen");
			$this->UnregisterVariable("Muell5LeerungInTagen");
			$this->UnregisterVariable("Muell5LeerungNaechsteAm");
			$this->UnregisterVariable("Muell5Termine");
		}
		
		if (($this->ReadPropertyString("Muell6Name") != "") AND ($this->ReadPropertyString("Muell6Art") != ""))
		{
			$this->RegisterVariableBoolean("Muell6LeerungHeute", $this->ReadPropertyString("Muell6Name")." - Leerung Heute", "Muell.NeinJa", 16);
			$this->RegisterVariableBoolean("Muell6LeerungMorgen", $this->ReadPropertyString("Muell6Name")." - Leerung Morgen", "Muell.NeinJa", 16);
			$this->RegisterVariableString("Muell6LeerungNaechsteAm", $this->ReadPropertyString("Muell6Name")." - Nächste Leerung am", "", 16);
			$this->RegisterVariableInteger("Muell6LeerungInTagen", $this->ReadPropertyString("Muell6Name")." - Nächste Leerung in", "", 16);
			$this->RegisterVariableString("Muell6Termine", $this->ReadPropertyString("Muell6Name")." - Termine", "~TextBox", 16);
			IPS_SetIcon($this->GetIDForIdent("Muell6LeerungInTagen"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell6LeerungNaechsteAm"), "Calendar");
			IPS_SetIcon($this->GetIDForIdent("Muell6Termine"), "Calendar");
			
			IPS_SetName($this->GetIDForIdent("Muell6LeerungHeute"), $this->ReadPropertyString("Muell6Name")." - Leerung Heute");
			IPS_SetName($this->GetIDForIdent("Muell6LeerungMorgen"), $this->ReadPropertyString("Muell6Name")." - Leerung Morgen");
			IPS_SetName($this->GetIDForIdent("Muell6LeerungInTagen"), $this->ReadPropertyString("Muell6Name")." - Nächste Leerung in");
			IPS_SetName($this->GetIDForIdent("Muell6LeerungNaechsteAm"), $this->ReadPropertyString("Muell6Name")." - Nächste Leerung am");
			IPS_SetName($this->GetIDForIdent("Muell6Termine"), $this->ReadPropertyString("Muell6Name")." - Termine");
			
			$this->EnableAction("Muell6Termine");
		}
		else
		{
			$this->UnregisterVariable("Muell6LeerungHeute");
			$this->UnregisterVariable("Muell6LeerungMorgen");
			$this->UnregisterVariable("Muell6LeerungInTagen");
			$this->UnregisterVariable("Muell6LeerungNaechsteAm");
			$this->UnregisterVariable("Muell6Termine");
		}
		
		// Allgemeine Variable
		$this->RegisterVariableBoolean("MuellXLeerungHeute", "Allgemein - Leerung Heute", "Muell.NeinJa", 10);
		$this->RegisterVariableBoolean("MuellXLeerungMorgen", "Allgemein - Leerung Morgen", "Muell.NeinJa", 10);

		
	    //Timer erstellen für automatische Entfernung vergangener Mülltermine
		if ($this->ReadPropertyBoolean("VergangeneTermineLoeschenCB") === true)
		{
			$this->RegisterTimer_Uhrzeit("MUELL_VergangeneTermineEntfernen", "MUELL_VergangeneTermineEntfernen", 23, 59, 51, 'MUELL_VergangeneTermineEntfernen($_IPS[\'TARGET\']);');
		}
		else
		{
			$this->UnregisterTimer("MUELL_VergangeneTermineEntfernen");
		}
		
		//Timer erstellen für Variablen-Update und Leerungs-Benachrichtigungen
        $this->RegisterTimer_Uhrzeit("MUELL_UpdateTimer", "MUELL_UpdateTimer", 0, 0, 1, 'MUELL_Update($_IPS[\'TARGET\']);');
		
        if ($this->ReadPropertyString("UhrzeitBenachrHeute1") != "")
		{
			$UhrzeitHeute1 = explode(":", $this->ReadPropertyString("UhrzeitBenachrHeute1"));
			$this->RegisterTimer_Uhrzeit("MUELL_BenachrichtigungHeute1", "MUELL_BenachrichtigungHeute1", $UhrzeitHeute1[0], $UhrzeitHeute1[1], 0, 'MUELL_BenachrichtigungHeuteMorgen($_IPS[\'TARGET\'], "Heute");');
		}
		else
		{
			$this->UnregisterTimer("MUELL_BenachrichtigungHeute1");
		}
		
		if ($this->ReadPropertyString("UhrzeitBenachrHeute2") != "")
		{
			$UhrzeitHeute2 = explode(":", $this->ReadPropertyString("UhrzeitBenachrHeute2"));
			$this->RegisterTimer_Uhrzeit("MUELL_BenachrichtigungHeute2", "MUELL_BenachrichtigungHeute2", $UhrzeitHeute2[0], $UhrzeitHeute2[1], 0, 'MUELL_BenachrichtigungHeuteMorgen($_IPS[\'TARGET\'], "Heute");');
		}
		else
		{
			$this->UnregisterTimer("MUELL_BenachrichtigungHeute2");
		}
		
		if ($this->ReadPropertyString("UhrzeitBenachrMorgen1") != "")
		{
			$UhrzeitMorgen1 = explode(":", $this->ReadPropertyString("UhrzeitBenachrMorgen1"));
			$this->RegisterTimer_Uhrzeit("MUELL_BenachrichtigungMorgen1", "MUELL_BenachrichtigungMorgen1", $UhrzeitMorgen1[0], $UhrzeitMorgen1[1], 0, 'MUELL_BenachrichtigungHeuteMorgen($_IPS[\'TARGET\'], "Morgen");');
		}
		else
		{
			$this->UnregisterTimer("MUELL_BenachrichtigungMorgen1");
		}
		
		if ($this->ReadPropertyString("UhrzeitBenachrMorgen2") != "")
		{
			$UhrzeitMorgen2 = explode(":", $this->ReadPropertyString("UhrzeitBenachrMorgen2"));
			$this->RegisterTimer_Uhrzeit("MUELL_BenachrichtigungMorgen2", "MUELL_BenachrichtigungMorgen2", $UhrzeitMorgen2[0], $UhrzeitMorgen2[1], 0, 'MUELL_BenachrichtigungHeuteMorgen($_IPS[\'TARGET\'], "Morgen");');
		}
		else
		{
			$this->UnregisterTimer("MUELL_BenachrichtigungMorgen2");
		}
		
		
		//Update
		$this->Update();
    }

    public function Update()
    {
		for ($i=1; $i<=6; $i++)
		{
			if (($this->ReadPropertyString("Muell".$i."Art") != "") AND ($this->ReadPropertyString("Muell".$i."Name") != ""))
			{
				$this->SetValueString("Muell".$i."Termine", $this->ReadPropertyString("Muell".$i."Termine"));
			}
		}
		
		$tomorrow = date("d.m.Y", strtotime("+1 day"));
		$today = date("d.m.Y");
		$today_timestamp = strtotime($today);
		
		$VarCheckHeute = 0;
		$VarCheckMorgen = 0;
		
		for ($i=1; $i<=6; $i++)
		{
			if (($this->ReadPropertyString("Muell".$i."Termine") != "") AND ($this->ReadPropertyString("Muell".$i."Name") != "") AND ($this->ReadPropertyString("Muell".$i."Art") != ""))
			{
				$Muell_AR["Muell".$i."_Art"] = $this->ReadPropertyString("Muell".$i."Art");
				$Muell_AR["Muell".$i."_Name"] = $this->ReadPropertyString("Muell".$i."Name");
				$Muell_Tage = explode("|", $this->ReadPropertyString("Muell".$i."Termine"));
				
				//Leerung Heute
				if (array_search ($today , $Muell_Tage) !== FALSE)
				{
					$this->SetValueBoolean("Muell".$i."LeerungHeute", true);
					$Muell_AR["Muell".$i."_Leerung_Heute"] = true;
					$VarCheckHeute = 1;
				}
				else
				{
					$this->SetValueBoolean("Muell".$i."LeerungHeute", false);
					$Muell_AR["Muell".$i."_Leerung_Heute"] = false;
				}
				
				//Leerung Morgen
				if (array_search ($tomorrow , $Muell_Tage) !== FALSE)
				{
					$this->SetValueBoolean("Muell".$i."LeerungMorgen", true);
					$Muell_AR["Muell".$i."_Leerung_Morgen"] = true;
					$VarCheckMorgen = 1;
				}
				else
				{
					$this->SetValueBoolean("Muell".$i."LeerungMorgen", false);
					$Muell_AR["Muell".$i."_Leerung_Morgen"] = false;
				}
				
				//Tag der nächsten Leerung
				foreach ($Muell_Tage as $Muell_Tag)
				{
					$Muell_Tag_Timestamp = strtotime($Muell_Tag);
					if ($today_timestamp <= $Muell_Tag_Timestamp)
					{
						$Muell_next = date("d.m.Y", $Muell_Tag_Timestamp);
						$this->SetValueString("Muell".$i."LeerungNaechsteAm", $this->Tage_Englisch2Deutsch($Muell_next).", ".$Muell_next);
						
						//Tage bis zur nächsten Leerung
						$Muell_nexttage = abs(floor((time() - strtotime($Muell_next))/86400));
						$this->SetValueInteger("Muell".$i."LeerungInTagen", $Muell_nexttage);
						if ($Muell_nexttage == 1)
						{
							IPS_SetVariableCustomProfile($this->GetIDForIdent("Muell".$i."LeerungInTagen"), "Muell.Tag");
						}
						else
						{
							IPS_SetVariableCustomProfile($this->GetIDForIdent("Muell".$i."LeerungInTagen"), "Muell.Tagen");
						}
						break;
					}
					else
					{
						$this->SetValueString("Muell".$i."LeerungNaechsteAm", "n/a");
						$this->SetValueInteger("Muell".$i."LeerungInTagen", 0);
					}
				}
			}
		}
		
		if ($VarCheckHeute === 1)
		{
			$this->SetValueBoolean("MuellXLeerungHeute", true);
		}
		else
		{
			$this->SetValueBoolean("MuellXLeerungHeute", false);
		}
		
		if ($VarCheckMorgen === 1)
		{
			$this->SetValueBoolean("MuellXLeerungMorgen", true);
		}
		else
		{
			$this->SetValueBoolean("MuellXLeerungMorgen", false);
		}
				
		
		if (@$Muell_AR)
		{
			return $Muell_AR;
		}
		else
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER // Es konnten keine Informationen zu Müll-Leerungen erfasst werden!");
			return false;
		}
    }
		
	public function BenachrichtigungHeuteMorgen(string $HeuteMorgen)
    {
		if ($this->ReadPropertyInteger("BenachrichtigungsVar") != 0)
		{
			if (GetValueBoolean($this->ReadPropertyInteger("BenachrichtigungsVar")) === false)
			{
				IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung Heute/Morgen' wird nicht durchgeführt - auf Grund der Einstellung der Benachrichtigungs-Variable");
				return false;
			}
		}
		if ($this->ReadPropertyBoolean("MuellBenachrichtigungCBOX") === false)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung Heute/Morgen' wird nicht durchgeführt - auf Grund der allgemeinen Einstellung in der Modul-Instanz");
				return false;
			}
		
        $HeuteMorgen = strtolower($HeuteMorgen);
        if (($HeuteMorgen != "heute") AND ($HeuteMorgen != "morgen"))
        {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER // Gültige Eingaben für die Variable 'HeuteMorgen' sind 'Heute' und 'Morgen'.");
            return false;
        }

        // Das Array für Müllleerungen initialisieren
        $MuellLeerungenNrAR = []; 
		
		switch ($HeuteMorgen)
		{
			case "heute":
				for ($i=1; $i<=6; $i++)
				{
					if (($this->ReadPropertyString("Muell".$i."Termine") != "") AND ($this->ReadPropertyString("Muell".$i."Name") != "") AND ($this->ReadPropertyString("Muell".$i."Art") != ""))
					{
						if (GetValueBoolean($this->GetIDForIdent("Muell".$i."LeerungHeute")) === true)
						{
							$MuellLeerungenNrAR[] = $i;
						}
					}
				}
				$BenachrichtigungsTextTemplate = $this->ReadPropertyString("MuellBenachrichtigungTEXTheute"); // Vorlage für Benachrichtigungstext
			break;
			
			case "morgen":
				for ($i=1; $i<=6; $i++)
				{
					if (($this->ReadPropertyString("Muell".$i."Termine") != "") AND ($this->ReadPropertyString("Muell".$i."Name") != "") AND ($this->ReadPropertyString("Muell".$i."Art") != ""))
					{
						if (GetValueBoolean($this->GetIDForIdent("Muell".$i."LeerungMorgen")) === true)
						{
							$MuellLeerungenNrAR[] = $i;
						}
					}
				}
				$BenachrichtigungsTextTemplate = $this->ReadPropertyString("MuellBenachrichtigungTEXTmorgen"); // Vorlage für Benachrichtigungstext
			break;
		}
		
		// Wenn keine Müllleerungen gefunden wurden, beenden
        if (empty($MuellLeerungenNrAR)) 
        {
            return false;
        }
		
        // Array zum Sammeln der Müllnamen für die kombinierte Nachricht
        $collectedMuellNames = []; 
		
		foreach ($MuellLeerungenNrAR as $MuellNr)
		{
			$MuellName = $this->ReadPropertyString("Muell".$MuellNr."Name");
			$MuellArt = $this->ReadPropertyString("Muell".$MuellNr."Art");
			$MuellTage = GetValueInteger($this->GetIDForIdent("Muell".$MuellNr."LeerungInTagen"));
			$MuellBenachrichtigungAktiv = $this->ReadPropertyBoolean("Muell".$MuellNr."Benachrichtigung");
			
            // Wenn die Benachrichtigung für diese Müllsorte deaktiviert ist, die nächste überspringen
			if ($MuellBenachrichtigungAktiv === false)
			{
				IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung Heute/Morgen' von Müll ".$MuellNr." wird nicht durchgeführt - auf Grund der Einstellung in der Modul-Instanz");
				continue; // Weiter zum nächsten Element in der Schleife
			}

            // Müllnamen für die kombinierte Nachricht sammeln
            $collectedMuellNames[] = $MuellName;
			
			$Muell_Tage_AR = explode("|", $this->ReadPropertyString("Muell".$MuellNr."Termine"));
			$today = date("d.m.Y");
			$today_timestamp = strtotime($today);
            $MuellDatum = "n/a"; // Standardwert
            $MuellTag = "n/a";   // Standardwert
			foreach ($Muell_Tage_AR as $Muell_Tag)
			{
				$Muell_Tag_Timestamp = strtotime($Muell_Tag);
				if ($today_timestamp <= $Muell_Tag_Timestamp)
				{
					$MuellDatum = date("d.m.Y", $Muell_Tag_Timestamp);
					$MuellTag = $this->Tage_Englisch2Deutsch($MuellDatum);
					break(1);
				}
			}
		
			// Individuellen Text generieren (relevant für "Eigene Aktion")
			$search = array("§MUELLNAME", "§MUELLART", "§MUELLDATUM", "§MUELLTAG", "§MUELLINTAGEN");
			$replace = array($MuellName, $MuellArt, $MuellDatum, $MuellTag, $MuellTage);

			$IndividualText = str_replace($search, $replace, $BenachrichtigungsTextTemplate);
			$IndividualText = str_replace('Â', '', $IndividualText);
			
			//EIGENE-AKTION - Bleibt in der Schleife, da sie individuelle Details erwartet
			if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
			{
				$SkriptID = $this->ReadPropertyInteger("EigenesSkriptID");
				if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
				{
					IPS_RunScriptEx($SkriptID, array("MUELL_Name" => $MuellName, "MUELL_Art" => $MuellArt, "MUELL_Datum" => $MuellDatum, "MUELL_Tag" => $MuellTag, "MUELL_InTagen" => $MuellTage, "MUELL_Text" => $IndividualText));
				}		
			}
      	} // Ende der foreach-Schleife
		
        // Kombinierte Nachricht aus den gesammelten Müllnamen zusammenbauen
        $combinedNamesString = "";
        if (count($collectedMuellNames) > 1) {
            $lastMuell = array_pop($collectedMuellNames); // Letztes Element entfernen
            $combinedNamesString = implode(", ", $collectedMuellNames) . " und " . $lastMuell; // Mit Kommas und "und" verbinden
        } else if (count($collectedMuellNames) == 1) {
            $combinedNamesString = $collectedMuellNames[0]; // Nur ein Element
        }

        // Wenn keine Müllnamen gesammelt wurden (z.B. alle Benachrichtigungen deaktiviert), beenden
        if (empty($combinedNamesString)) {
            return false;
        }

        // Grundtext der Benachrichtigung festlegen ("Heute wird" oder "Morgen wird")
        $baseMessage = "";
        if ($HeuteMorgen == "heute") {
            $baseMessage = "Heute wird ";
        } elseif ($HeuteMorgen == "morgen") {
            $baseMessage = "Morgen wird ";
        }
        $finalCombinedMessage = $baseMessage . $combinedNamesString . " abgeholt!";


        // --- Kombinierte PUSH-NACHRICHT senden ---
        if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
        {
            $WFinstanzID = $this->ReadPropertyInteger("WebFrontInstanceID");
            if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
            {
                $pushText = $finalCombinedMessage;
                // Push-Nachrichten sind auf 256 Zeichen begrenzt
                if (strlen($pushText) > 256)
                {
                    $pushText = substr($pushText, 0, 253) . "..."; // Kürzen und Auslassungspunkte hinzufügen
                    IPS_LogMessage("MUELLABFUHR-MODUL", "WARNUNG - Push-Nachricht wurde auf 256 Zeichen gekürzt.");
                }
                WFC_PushNotification($WFinstanzID, "Müllabfuhr", $pushText, "", 0);
            }
        }

        // --- Kombinierte EMAIL-NACHRICHT senden ---
        if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
        {
            $SMTPinstanzID = $this->ReadPropertyInteger("SmtpInstanceID");
            if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
            {
                SMTP_SendMail($SMTPinstanzID, "Müllabfuhr", $finalCombinedMessage);
            }
        }
		
		return true;
    }
	
	public function BenachrichtigungInTagen(string $Muellsorte)
    {
		if ($this->ReadPropertyInteger("BenachrichtigungsVar") != 0)
		{
			if (GetValueBoolean($this->ReadPropertyInteger("BenachrichtigungsVar")) === false)
			{
				IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung Heute/Morgen' wird nicht durchgeführt - auf Grund der Einstellung der Benachrichtigungs-Variable");
				return false;
			}
		}
		if ($this->ReadPropertyBoolean("MuellBenachrichtigungCBOX") === false)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung Heute/Morgen' wird nicht durchgeführt - auf Grund der allgemeinen Einstellung in der Modul-Instanz");
			return false;
		}
		
		$MuellNr = 0;
		if ((strlen($Muellsorte) == 1) AND ((int)$Muellsorte > 0))
		{
			$MuellNr = $Muellsorte;
		}
		else
		{
			for ($i=1; $i<=6; $i++)
			{
				if ((trim($this->ReadPropertyString("Muell".$i."Art")) == $Muellsorte) OR (trim($this->ReadPropertyString("Muell".$i."Name")) == $Muellsorte))
				{
					$MuellNr = $i;
					break;
				}
			}
		}
		
		if ($MuellNr === 0)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung in X Tagen' nicht möglich, weil die Müllart/-name/-nummer '".$Muellsorte."' nicht gefunden werden konnte!");
			return false;
		}

		$MuellBenachrichtigungAktiv = $this->ReadPropertyBoolean("Muell".$MuellNr."Benachrichtigung");
		
		if ($MuellBenachrichtigungAktiv === false)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Benachrichtung 'Leerung in X Tagen' von Müll ".$MuellNr." wird nicht durchgeführt - auf Grund der Einstellung in der Modul-Instanz");
			return false;
		}
		
		$MuellName = $this->ReadPropertyString("Muell".$MuellNr."Name");
		$MuellArt = $this->ReadPropertyString("Muell".$MuellNr."Art");
		$MuellTage = GetValueInteger($this->GetIDForIdent("Muell".$MuellNr."LeerungInTagen"));

		$Muell_Tage_AR = explode("|", $this->ReadPropertyString("Muell".$MuellNr."Termine"));
		$tomorrow = date("d.m.Y", strtotime("+1 day"));
		$today = date("d.m.Y");
		$today_timestamp = strtotime($today);
		foreach ($Muell_Tage_AR as $Muell_Tag)
		{
			$Muell_Tag_Timestamp = strtotime($Muell_Tag);
			if ($today_timestamp <= $Muell_Tag_Timestamp)
			{
				$MuellDatum = date("d.m.Y", $Muell_Tag_Timestamp);
				$MuellTag = $this->Tage_Englisch2Deutsch($MuellDatum);
				break;
			}
		}
		
		$BenachrichtigungsText = $this->ReadPropertyString("MuellBenachrichtigungTEXTinTagen");
	
		//Code-Wörter austauschen gegen gewünschte Werte
		$search = array("§MUELLNAME", "§MUELLART", "§MUELLDATUM", "§MUELLTAG", "§MUELLINTAGEN");
		$replace = array($MuellName, $MuellArt, $MuellDatum, $MuellTag, $MuellTage);

		$Text = str_replace($search, $replace, $BenachrichtigungsText);
		$Text = str_replace('Â', '', $Text);
		
		//PUSH-NACHRICHT
		if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
		{
			$WFinstanzID = $this->ReadPropertyInteger("WebFrontInstanceID");
			if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
			{
				if (strlen($Text) <= 256)
				{
					WFC_PushNotification($WFinstanzID, "Müllabfuhr", $Text, "", 0);
				}
				else
				{
					 IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER!!! - Die Textlänge einer Push-Nachricht darf maximal 256 Zeichen betragen!!!");
				}
			}
		}
	
		//EMAIL-NACHRICHT
		if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
		{
			$SMTPinstanzID = $this->ReadPropertyInteger("SmtpInstanceID");
			if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
			{
				SMTP_SendMail($SMTPinstanzID, "Müllabfuhr", $Text);
			}		
		}
		
		//EIGENE-AKTION
		if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
		{
			$SkriptID = $this->ReadPropertyInteger("EigenesSkriptID");
			if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
			{
				IPS_RunScriptEx($SkriptID, array("MUELL_Name" => $MuellName, "MUELL_Art" => $MuellArt, "MUELL_Datum" => $MuellDatum, "MUELL_Tag" => $MuellTag, "MUELL_InTagen" => $MuellTage, "MUELL_Text" => $Text));
			}		
		}
		
		return true;
    }
	
	public function AbfrageMuellsorte(string $Muellsorte)
	{
		$MuellNr = 0;
		if ((strlen($Muellsorte) == 1) AND ((int)$Muellsorte > 0))
		{
			$MuellNr = $Muellsorte;
		}
		else
		{
			for ($i=1; $i<=6; $i++)
			{
				if ((trim($this->ReadPropertyString("Muell".$i."Art")) == $Muellsorte) OR (trim($this->ReadPropertyString("Muell".$i."Name")) == $Muellsorte))
				{
					$MuellNr = $i;
					break;
				}
			}
		}
		
		if ($MuellNr === 0)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Abfrage der Müllsorte nicht möglich, weil die Müllart/-name/-nummer '".$Muellsorte."' nicht gefunden werden konnte!");
			return false;
		}
		
		$Muell_Tage_AR = explode("|", $this->ReadPropertyString("Muell".$MuellNr."Termine"));
		$tomorrow = date("d.m.Y", strtotime("+1 day"));
		$today = date("d.m.Y");
		$today_timestamp = strtotime($today);
		foreach ($Muell_Tage_AR as $Muell_Tag)
		{
			$Muell_Tag_Timestamp = strtotime($Muell_Tag);
			if ($today_timestamp <= $Muell_Tag_Timestamp)
			{
				$MuellDatum = date("d.m.Y", $Muell_Tag_Timestamp);
				$MuellTag = $this->Tage_Englisch2Deutsch($MuellDatum);
				break;
			}
		}
		
		$Muell_AR["Muell_Art"] = $this->ReadPropertyString("Muell".$MuellNr."Art");
		$Muell_AR["Muell_Name"] = $this->ReadPropertyString("Muell".$MuellNr."Name");
		$Muell_AR["Muell_Datum"] = $MuellDatum;
		$Muell_AR["Muell_Tag"] = $MuellTag;
		$Muell_AR["Muell_LeerungInTagen"] = GetValueInteger($this->GetIDForIdent("Muell".$MuellNr."LeerungInTagen"));  
		$Muell_AR["Muell_NaechsteLeerungAm"] = GetValueString($this->GetIDForIdent("Muell".$MuellNr."LeerungNaechsteAm"));
		
		return $Muell_AR;
	}
	
	public function TerminEintragungMuellsorte(int $muellsorteNr, string $termine)
	{
        // Sicherstellen, dass die Müllsortennummer gültig ist
        if ($muellsorteNr < 1 || $muellsorteNr > 6) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: Ungültige Müllsortennummer: " . $muellsorteNr);
            return false;
        }

        // Überprüfen, ob Name und Art für diese Müllsorte konfiguriert sind
        $muellName = $this->ReadPropertyString("Muell".$muellsorteNr."Name");
        $muellArt = $this->ReadPropertyString("Muell".$muellsorteNr."Art");

        if (empty($muellName) && empty($muellArt)) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "ACHTUNG: Müllsorte ".$muellsorteNr." ist nicht vollständig konfiguriert (Name und Art fehlen). Termineintrag ignoriert.");
            return false;
        }

		IPS_SetProperty($this->InstanceID, "Muell".$muellsorteNr."Termine", trim($termine));
		IPS_ApplyChanges($this->InstanceID);		
		$this->Update();
		
		if (GetValueString($this->GetIDForIdent("Muell".$muellsorteNr."Termine")) == trim($termine))
		{
			return true;
		}
		else
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "ACHTUNG! Es gab ein Problem beim Eintragen der Termine für die Müllsorte '".$muellsorteNr."'");
			return false;
		}
	}
	
    public function BenachrichtigungsTest()
    {
		$MuellNr = 0;
		if ((trim($this->ReadPropertyString("Muell1Art")) != "") AND (trim($this->ReadPropertyString("Muell1Name")) != ""))
		{
			$MuellNr = 1;
		}
		if ($MuellNr === 0)
		{
			IPS_LogMessage("MUELLABFUHR-MODUL", "Test-Benachrichtung nicht möglich, weil die Müllsorte 1 in der Modul-Instanz nicht ausgefüllt ist!");
			return false;
		}
		
		$MuellName = $this->ReadPropertyString("Muell1Name");
		$MuellArt = $this->ReadPropertyString("Muell1Art");
		$MuellTage = GetValueInteger($this->GetIDForIdent("Muell1LeerungInTagen"));
		$MuellBenachrichtigungAktiv = $this->ReadPropertyBoolean("Muell1Benachrichtigung");
		$MuellBenachrichtigungAllgemeinAktiv = $this->ReadPropertyBoolean("MuellBenachrichtigungCBOX");
		$BenachrichtigungsText = $this->ReadPropertyString("MuellBenachrichtigungTEXTinTagen");
		
		$Muell_Tage_AR = explode("|", $this->ReadPropertyString("Muell".$MuellNr."Termine"));
		$tomorrow = date("d.m.Y", strtotime("+1 day"));
		$today = date("d.m.Y");
		$today_timestamp = strtotime($today);
		foreach ($Muell_Tage_AR as $Muell_Tag)
		{
			$Muell_Tag_Timestamp = strtotime($Muell_Tag);
			if ($today_timestamp <= $Muell_Tag_Timestamp)
			{
				$MuellDatum = date("d.m.Y", $Muell_Tag_Timestamp);
				$MuellTag = $this->Tage_Englisch2Deutsch($MuellDatum);
				break;
			}
		}
		
		//Code-Wörter austauschen gegen gewünschte Werte
		$search = array("§MUELLNAME", "§MUELLART", "§MUELLDATUM", "§MUELLTAG", "§MUELLINTAGEN");
		$replace = array($MuellName, $MuellArt, $MuellDatum, $MuellTag, $MuellTage);

		$Text = str_replace($search, $replace, $BenachrichtigungsText);
		$Text = str_replace('Â', '', $Text);
		
		//PUSH-NACHRICHT
		if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
		{
			$WFinstanzID = $this->ReadPropertyInteger("WebFrontInstanceID");
			if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
			{
				if (strlen($Text) <= 256)
				{
					WFC_PushNotification($WFinstanzID, "Müllabfuhr", $Text, "", 0);
				}
				else
				{
					 IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER!!! - Die Textlänge einer Push-Nachricht darf maximal 256 Zeichen betragen!!!");
				}
			}
		}
	
		//EMAIL-NACHRICHT
		if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
		{
			$SMTPinstanzID = $this->ReadPropertyInteger("SmtpInstanceID");
			if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
			{
				SMTP_SendMail($SMTPinstanzID, "Müllabfuhr", $Text);
			}		
		}
		
		//EIGENE-AKTION
		if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
		{
			$SkriptID = $this->ReadPropertyInteger("EigenesSkriptID");
			if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
			{
				IPS_RunScriptEx($SkriptID, array("MUELL_Name" => $MuellName, "MUELL_Art" => $MuellArt, "MUELL_Datum" => $MuellDatum, "MUELL_Tag" => $MuellTag, "MUELL_InTagen" => $MuellTage, "MUELL_Text" => $Text));
			}		
		}
		
		return true;
   	}
	
	public function RequestAction($VarIdent, $Value)
    {
		IPS_SetProperty($this->InstanceID, $VarIdent, $Value);
		IPS_ApplyChanges($this->InstanceID);
		$this->Update();
	}
	
	private function MuellterminePruefen()
	{
		for ($i=1; $i<=6; $i++)
		{
			if ($this->ReadPropertyString("Muell".$i."Termine") != "")
			{
				$MuelltermineTestAR = explode("|", $this->ReadPropertyString("Muell".$i."Termine"));
				foreach ($MuelltermineTestAR as $MuellterminTestDatum)
				{
					if ($this->MuellterminDatumPruefen($MuellterminTestDatum) == false)
					{
						return false;
					}
				}
			}
		}
		return true;
	}

	private function MuellterminDatumPruefen($date, $format = 'd.m.Y')
	{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
	}
	
	public function VergangeneTermineEntfernen()
	{ 
		for ($i=1; $i<=6; $i++)
		{
			if ($this->ReadPropertyString("Muell".$i."Termine") != "")
			{
				$MuelltermineAlteEntfernenAR = explode("|", $this->ReadPropertyString("Muell".$i."Termine"));
				$ArrayCount = count($MuelltermineAlteEntfernenAR);
				$MuelltermineNeu = "";
				for ($c=0; $c<$ArrayCount; $c++)
				{
					$DatumAltOderNeu = $MuelltermineAlteEntfernenAR[$c];
					$DatumAltOderNeu = strtotime($DatumAltOderNeu);
					$DatumHeute = strtotime(date("d.m.Y"));
					
					if ($DatumAltOderNeu > $DatumHeute)
					{
						if ($MuelltermineNeu != "")
						{
							$MuelltermineNeu =  $MuelltermineNeu."|".date("d.m.Y", $DatumAltOderNeu);
						}
						else
						{
							$MuelltermineNeu = date("d.m.Y", $DatumAltOderNeu); 
						}
					}
				}
				if ($MuelltermineNeu != "")
				{
					IPS_SetProperty($this->InstanceID, "Muell".$i."Termine", $MuelltermineNeu);
					IPS_ApplyChanges($this->InstanceID);
				}
			}
		}
	}
	
    private function SetValueBoolean($Ident, $Value)
    {
        $ID = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($ID) <> $Value)
        {
            SetValueBoolean($ID, boolval($Value));
            return true;
        }
        return false;
    }
	
	private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }
    
    private function SetValueString($Ident, $Value)
    {
        $ID = $this->GetIDForIdent($Ident);
        if (GetValueString($ID) <> $Value)
        {
            SetValueString($ID, strval($Value));
            return true;
        }
        return false;
    }

	private function Tage_Englisch2Deutsch($Datum)
	{
		$WochentagEnglisch = date("l", strtotime($Datum));
		switch($WochentagEnglisch)
		{
			case "Monday":
				$TagDeutsch = "Montag";
			break;
			case "Tuesday":
				$TagDeutsch = "Dienstag";
			break;
			case "Wednesday":
				$TagDeutsch = "Mittwoch";
			break;
			case "Thursday":
				$TagDeutsch = "Donnerstag";
			break;
			case "Friday":
				$TagDeutsch = "Freitag";
			break;
			case "Saturday":
				$TagDeutsch = "Samstag";
			break;
			case "Sunday":
				$TagDeutsch = "Sonntag";
			break;
		}
		return $TagDeutsch;
	}

    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 0)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
    
    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
	{
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
        
    }
	
	protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
		if (@!IPS_VariableProfileExists($Name))
		{
			IPS_CreateVariableProfile($Name, 1);
		}
		else
		{
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != 1)
				throw new Exception("Variable profile type does not match for profile " . $Name);
		}
		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	}
		
	protected function RegisterTimer_Uhrzeit($Ident, $Name, $Stunde, $Minute, $Sekunde, $Skript)
	{
	   $eid = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
	   if($eid === false)
	   {
			$eid = IPS_CreateEvent(1);
			IPS_SetParent($eid, $this->InstanceID);
			IPS_SetName($eid, $Name);
			IPS_SetIdent($eid, $Ident);
			IPS_SetHidden($eid, true);
			IPS_SetEventScript($eid, $Skript);
			IPS_SetInfo($eid, "this timer was created by script #".$_IPS['SELF']);
			IPS_SetEventCyclicTimeFrom($eid, $Stunde, $Minute, $Sekunde);
			IPS_SetEventActive($eid, true);
			return $eid;
	   }
	   else
	   {
			IPS_SetEventCyclicTimeFrom($eid, $Stunde, $Minute, $Sekunde);
			IPS_SetEventActive($eid, true);
			return $eid;
	   }
	}

    protected function UnregisterTimer($Ident)
    {
        $id = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($id > 0)
        {
            if (@!IPS_EventExists($id))
                throw new Exception('Timer not present', E_USER_NOTICE);
            IPS_DeleteEvent($id);
        }
    }

    public function ImportIcsTermine(string $filePath)
    {
        // Pfad validieren und Dateiinhalt lesen
        if (!file_exists($filePath)) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: ICS-Datei nicht gefunden unter: " . $filePath);
            $this->SetStatus(205); 
            return false;
        }

        $icsContent = file_get_contents($filePath);
        if ($icsContent === false) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: Konnte ICS-Datei nicht lesen: " . $filePath);
            $this->SetStatus(205);
            return false;
        }

        // Pfad der zuletzt importierten Datei speichern (für optionalen Re-Import nach Übernahme)
        IPS_SetProperty($this->InstanceID, "LastImportedIcsFilePath", $filePath);
        IPS_ApplyChanges($this->InstanceID); // Änderungen sofort anwenden, um den Pfad zu speichern

        // Dynamisches Mapping von konfigurierten Müllnamen/-arten zu MüllNr erstellen
        $muellTypeMapping = [];
        $muellNamesConfigured = []; // Zum Loggen der konfigurierten Namen
        for ($i = 1; $i <= 6; $i++) {
            $name = $this->ReadPropertyString("Muell" . $i . "Name");
            $art = $this->ReadPropertyString("Muell" . $i . "Art");
            if (!empty($name)) {
                $muellTypeMapping[mb_strtolower($name)] = $i; // Zuordnung in Kleinbuchstaben
                $muellNamesConfigured[$i][] = $name;
            }
            if (!empty($art)) {
                $muellTypeMapping[mb_strtolower($art)] = $i;
                $muellNamesConfigured[$i][] = $art;
            }
        }

        if (empty($muellTypeMapping)) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: Keine Müllarten in den Moduleinstellungen konfiguriert (Name/Art). Automatischer ICS-Import nicht möglich.");
            $this->SetStatus(205);
            return false;
        }
        IPS_LogMessage("MUELLABFUHR-MODUL", "Konfigurierte Müllarten für Zuordnung: " . json_encode($muellNamesConfigured));


        $collectedDatesByMuellNr = [];
        for ($i = 1; $i <= 6; $i++) {
            $collectedDatesByMuellNr[$i] = []; // Leeres Array für jede mögliche MüllNr initialisieren
        }

        $lines = explode("\n", $icsContent);
        $currentSummary = '';
        $currentDate = '';

        $unrecognizedSummariesThisImport = []; // Sammelt unbekannte SUMMARYs aus diesem Import

        IPS_LogMessage("MUELLABFUHR-MODUL", "Starte Parsen der ICS-Datei für automatische Zuordnung...");

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, 'BEGIN:VEVENT') !== false) {
                $currentSummary = '';
                $currentDate = '';
            } elseif (strpos($line, 'SUMMARY:') === 0) {
                $currentSummary = substr($line, strlen('SUMMARY:'));
                $currentSummary = str_replace('\,', ',', $currentSummary); // Kommas entescapen
				$currentSummary = preg_replace('/[[:cntrl:]]/', '', $currentSummary); // Alle Steuerzeichen entfernen
   				$currentSummary = trim($currentSummary); // Leerzeichen am Anfang/Ende entfernen
            } elseif (strpos($line, 'DTSTART;VALUE=DATE:') === 0) {
                $dateString = substr($line, strlen('DTSTART;VALUE=DATE:')); //YYYYMMDD
                if (strlen($dateString) == 8 && is_numeric($dateString)) {
                    $year = substr($dateString, 0, 4);
                    $month = substr($dateString, 4, 2);
                    $day = substr($dateString, 6, 2);
                    $currentDate = $day . '.' . $month . '.' . $year; // TT.MM.JJJJ
                }
            } elseif (strpos($line, 'END:VEVENT') !== false) {
                if (!empty($currentSummary) && !empty($currentDate)) {
                    $lowerSummary = mb_strtolower($currentSummary);
                    if (isset($muellTypeMapping[$lowerSummary])) {
                        $matchedMuellNr = $muellTypeMapping[$lowerSummary];
                        if ($this->MuellterminDatumPruefen($currentDate)) {
                            $collectedDatesByMuellNr[$matchedMuellNr][] = $currentDate;
                            // IPS_LogMessage("MUELLABFUHR-MODUL", "Termin zugeordnet: '" . $currentSummary . "' zu Müll " . $matchedMuellNr . " am " . $currentDate);
                        } else {
                            IPS_LogMessage("MUELLABFUHR-MODUL", "WARNUNG: Ungültiges Datum '" . $currentDate . "' für '" . $currentSummary . "' in ICS-Datei gefunden. Termin ignoriert.");
                        }
                    } else {
                        IPS_LogMessage("MUELLABFUHR-MODUL", "WARNUNG: ICS-Ereignis '" . $currentSummary . "' konnte keiner konfigurierten Müllart zugeordnet werden. Termin ignoriert.");
                        $unrecognizedSummariesThisImport[] = $currentSummary; // Sammle unbekannte SUMMARYs
                    }
                }
            }
        }

        // Nach dem Parsen: Unbekannte SUMMARYs in Property speichern (Duplikate vermeiden)
        $existingUnrecognizedJson = $this->ReadPropertyString("UnrecognizedIcsSummaries");
        $existingUnrecognized = json_decode($existingUnrecognizedJson, true);
        if (!is_array($existingUnrecognized)) {
            $existingUnrecognized = [];
        }

        $mergedUnrecognized = array_unique(array_merge($existingUnrecognized, $unrecognizedSummariesThisImport));
        $this->WritePropertyString("UnrecognizedIcsSummaries", json_encode(array_values($mergedUnrecognized))); // Speichern und Indizes neu ordnen


        $importSuccessCount = 0;
        $importFailureCount = 0;

        foreach ($collectedDatesByMuellNr as $muellNr => $dates) {
            if (!empty($dates)) {
                $uniqueDates = array_unique($dates);
                usort($uniqueDates, function($a, $b) {
                    return strtotime(str_replace('.', '-', $a)) - strtotime(str_replace('.', '-', $b));
                });
                $termineString = implode("|", $uniqueDates);

                $muellName = $this->ReadPropertyString("Muell".$muellNr."Name"); // Konfigurierten Namen für Logging holen
                if (empty($muellName)) { // Sicherheit, falls Name doch leer
                    $muellName = "Müll ".$muellNr;
                }

                // Termine eintragen (überschreibt bestehende Termine für diese Müllsorte!)
                if ($this->TerminEintragungMuellsorte($muellNr, $termineString)) {
                    IPS_LogMessage("MUELLABFUHR-MODUL", "ICS-Import erfolgreich für Müllsorte " . $muellNr . " ('" . $muellName . "'). " . count($uniqueDates) . " Termine importiert.");
                    $importSuccessCount++;
                } else {
                    IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: ICS-Termine für Müllsorte " . $muellNr . " ('" . $muellName . "') konnten nicht eingetragen werden.");
                    $importFailureCount++;
                }
            }
        }

        if ($importSuccessCount > 0) {
            $this->SetStatus(102); // Erfolgreicher Import
            IPS_LogMessage("MUELLABFUHR-MODUL", "ICS-Importvorgang abgeschlossen: " . $importSuccessCount . " Müllarten erfolgreich importiert, " . $importFailureCount . " fehlgeschlagen.");
            return true;
        } else {
            IPS_LogMessage("MUELLABFUHR-MODUL", "ICS-Importvorgang abgeschlossen: Keine Termine erfolgreich importiert.");
            $this->SetStatus(205); // Fehler, wenn nichts importiert wurde
            return false;
        }
    }

    // Gibt die Liste der unbekannten ICS-SUMMARYs zurück (für form.json)
    public function GetUnrecognizedIcsSummaries(): string
    {
        return $this->ReadPropertyString("UnrecognizedIcsSummaries");
    }

    // Übernimmt einen unbekannten SUMMARY in eine MuellXName-Eigenschaft
    public function AdoptUnrecognizedIcsSummary(string $summaryToAdopt, int $targetMuellNr)
    {
        if ($targetMuellNr < 1 || $targetMuellNr > 6) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "FEHLER: AdoptUnrecognizedIcsSummary - Ungültige Ziel-Müllnummer: " . $targetMuellNr);
            return false;
        }

        // Setze die MuellXName Eigenschaft
        // Hinweis: Derzeit wird nur MuellXName gesetzt. Wenn MuellXArt bevorzugt, müsste das hier angepasst werden.
        IPS_SetProperty($this->InstanceID, "Muell" . $targetMuellNr . "Name", $summaryToAdopt);

        // Entferne den übernommenen Vorschlag aus der Liste der unbekannten Summaries
        $existingUnrecognizedJson = $this->ReadPropertyString("UnrecognizedIcsSummaries");
        $existingUnrecognized = json_decode($existingUnrecognizedJson, true);
        if (is_array($existingUnrecognized)) {
            $key = array_search($summaryToAdopt, $existingUnrecognized);
            if ($key !== false) {
                unset($existingUnrecognized[$key]);
                $this->WritePropertyString("UnrecognizedIcsSummaries", json_encode(array_values($existingUnrecognized))); // Speichern und Indizes neu ordnen
            }
        }

        IPS_ApplyChanges($this->InstanceID); // Konfigurationsänderungen anwenden
        IPS_LogMessage("MUELLABFUHR-MODUL", "ICS-Vorschlag übernommen: '" . $summaryToAdopt . "' zugewiesen zu Muell" . $targetMuellNr . "Name.");

        // Optional: Nach Übernahme des Vorschlags den letzten Import automatisch erneut ausführen, um Termine zu aktualisieren
        $lastFilePath = $this->ReadPropertyString("LastImportedIcsFilePath");
        if (!empty($lastFilePath) && file_exists($lastFilePath)) {
            IPS_LogMessage("MUELLABFUHR-MODUL", "ICS-Import wird nach Übernahme des Vorschlags automatisch erneut ausgeführt, um Termine zu aktualisieren.");
            $this->ImportIcsTermine($lastFilePath); // Import erneut ausführen
        } else {
            IPS_LogMessage("MUELLABFUHR-MODUL", "WARNUNG: ICS-Import konnte nach Übernahme des Vorschlags nicht automatisch erneut ausgeführt werden (kein letzter Dateipfad bekannt oder Datei nicht gefunden). Bitte manuell erneut importieren.");
        }

        return true;
    }
}
