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
			
				if ( ($currentDeviceProfile == "~Intensity.255") || ($currentDeviceCustomProfile == "~Intensity.255") || ($currentDeviceProfile == "Intensity.Hue") || ($currentDeviceCustomProfile == "Intensity.Hue") ) {
				
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

		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {
			
			// Check if the target device is a HUE device
			$variableDetails = IPS_GetVariable($currentDevice);
			if ( ($variableDetails['VariableProfile'] == "Intensity.Hue") || ($variableDetails['VariableProfile'] == "~Intensity.255") ) {
				
				$newIntensity = round($newIntensity * 2.55);
			}
			
			$result = RequestAction($currentDevice, $newIntensity);
			
			if (! $result) {

				IPS_LogMessage($_IPS['SELF'],"METADIM - Set Intensity not possible for device $currentDevice - could not identify instance type");
			}

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
