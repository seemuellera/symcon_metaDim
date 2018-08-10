<?php

    // Klassendefinition
    class MetaDim extends IPSModule {
 
        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);
 
            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            
		// Diese Zeile nicht löschen.
            	parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","MetaDim");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("DimStep",10);

		// Variables
		$this->RegisterVariableInteger("Intensity","Intensity","~Intensity.100");
		$this->RegisterVariableString("Devices","Devices");

		// Default Actions
		$this->EnableAction("Intensity");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'METADIM_RefreshInformation($_IPS[\'TARGET\']);');

        }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {

		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		

            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
        }


	public function GetConfigurationForm() {

        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
			"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "DimStep", "caption" => "Dimming Step size");
		

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'METADIM_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "HorizontalSlider", "name" => "TestIntensity", "minimum" => 0, "maximum" => 100, "onChange" => 'METADIM_SetIntensity($id,$TestIntensity);');
		$form['actions'][] = Array("type" => "Button", "label" => "Increase Intensity", "onClick" => 'METADIM_IncreaseIntensity($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Decrease Intensity", "onClick" => 'METADIM_DecreaseIntensity($id);');

		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		// If all devices are off the default intensity is 0%
		$maxIntensity = 0;
	
		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {

			// Now we need to determin which type of intensity we have (max 100 or max 255)
			$currentDeviceDetails = IPS_GetVariable($currentDevice);
			$currentDeviceProfile = $currentDeviceDetails['VariableProfile'];
			$currentDeviceCustomProfile = $currentDeviceDetails['VariableCustomProfile'];

			if ( ($currentDeviceProfile == "~Intensity.100") || ($currentDeviceCustomProfile == "~Intensity.100") ) {
			
				$currentDeviceIntensity = GetValue($currentDevice);
			}
			else {
			
				if ( ($currentDeviceProfile == "~Intensity.255") || ($currentDeviceCustomProfile == "~Intensity.255") || ($currentDeviceProfile == "Intensity.HUE") || ($currentDeviceCustomProfile == "Intensity.HUE") ) {
				
					$currentDeviceIntensity = round(GetValue($currentDevice) / 2.55, 0);
				}
				else {
				
					IPS_LogMessage($_IPS['SELF'],"METADIM - Get Intensity not possible for device $currentDevice - Variable profile was not found");
				}
			}
		
			// If one device is on we set the status to on
			if ($currentDeviceIntensity > $maxIntensity ) {
			
				$maxIntensity = $currentDeviceIntensity;	
			}
		}

		SetValue($this->GetIDForIdent("Intensity"), $maxIntensity);

	}

	public function SetIntensity($newIntensity) {

		// Some Devices Dim at 0 to 255 instead of 0 to 100. Therefore we calculate another intensity
		$newIntensity255 = round($newIntensity * 2.55, 0);

		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {
		
			$currentDeviceDetails = IPS_GetVariable($currentDevice);
			$parentId = $currentDeviceDetails['VariableAction'];

			if (! IPS_InstanceExists($parentId) ) {
				                        
		        	IPS_LogMessage($_IPS['SELF'],"METADIM - Set Intensity not possible for device $currentDevice - parent instance was not found");
				// Now we skip this device
				continue;
			}

			// Now we need to find out which device type we have to deal with
			$parentDetails = IPS_GetInstance($parentId);
			$parentModuleName = $parentDetails['ModuleInfo']['ModuleName'];

			if (preg_match('/Z-Wave/', $parentModuleName) ) {

				ZW_DimSet($parentId, $newIntensity);
				continue;
			}

			if (preg_match('/HUELight/', $parentModuleName) ) {

				// HUE devices dont turn off when intensity reaches 0
				if ($newIntensity255 > 0) {

					HUE_SetBrightness($parentId, $newIntensity255);
				}
				else {
				
					HUE_SetState($parentId, false);
				}
				continue;
			}

			if (preg_match('/MetaDim/', $parentModuleName) ) {

				METADIM_SetIntensity($parentId, $newIntensity);
				continue;
			}

			IPS_LogMessage($_IPS['SELF'],"METADIM - Set Intensity not possible for device $currentDevice - could not identify instance type");

		}
	}

	public function IncreaseIntensity() {
	
		$newIntensity = GetValue($this->GetIDForIdent("Intensity") ) + $this->ReadPropertyInteger("DimStep");
		if ($newIntensity > 100) { $newIntensity = 100; }
		$this->SetIntensity($newIntensity);
	}

	public function DecreaseIntensity() {
	
		$newIntensity = GetValue($this->GetIDForIdent("Intensity") ) - $this->ReadPropertyInteger("DimStep");
		if ($newIntensity < 0) { $newIntensity = 0; }
		$this->SetIntensity($newIntensity);
	}

	protected function GetDevices() {
	
		$allLinks = IPS_GetChildrenIDs($this->GetIDForIdent("Devices"));

		$allDevices = Array();

		foreach ($allLinks as $currentLink) {
		
			$currentLinkDetails = IPS_GetLink($currentLink);
			$allDevices[] = $currentLinkDetails['TargetID'];
		}

		return $allDevices;
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Intensity":
				// Default Action for Status Variable
				$this->setIntensity($Value);

				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}

    }
?>
