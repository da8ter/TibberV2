<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/functions.php';

	class Tibber extends IPSModule
	{
		use TibberHelper;

		private const HTML_FontSizeMin = 12;
		private const HTML_FontSizeMax = 20;
		private const HTML_FontSizeDef = 2;
		private const HTML_Color_White = 0xFFFFFF;
		private const HTML_Color_Grey = 0x808080;
		private const HTML_Color_Red = 0xFF0000;
		private const HTML_Color_Orange = 0xFF8000;
		private const HTML_Color_Mint = 0x28CDAB;
		private const HTML_Color_Darkmint = 0x1D8B75;
		private const HTML_Color_Black = 0x000000;

		private const HTML_Color_Green = 0x008000;
		private const HTML_Color_Darkgreen = 0x004000;
		private const HTML_Default_PX = 5;
		private const HTML_Default_HourAhead = 24;
		private const HTML_Max_HourAhead = 48;
		private const HTML_Bar_Price_Round = 2;
		private const HTML_Bar_Price_vis_ct = true;
		private const HTML_Hour_WriteMode = false;

		public function Create()
		{
			//Never delete this line!
			parent::Create();



			$this->RegisterPropertyBoolean("InstanceActive", true);
			$this->RegisterPropertyString("Token", '');
			$this->RegisterPropertyString("Api", 'https://api.tibber.com/v1-beta/gql');
			$this->RegisterPropertyString("Home_ID",'0');
			$this->RegisterPropertyBoolean("Price_log", false);
			$this->RegisterPropertyBoolean("Price_Variables", false);

			$this->RegisterPropertyBoolean("Statistics", false);
			$this->RegisterPropertyBoolean("Ahead_Price_Data_bool", false);
			
			$this->RegisterAttributeString("Homes", "");
			$this->RegisterAttributeString("Price_Array", '');
			$this->RegisterAttributeInteger("ar_handler", 0);
			$this->RegisterAttributeBoolean("EEX_Received", false);
			$this->RegisterAttributeString('AVGPrice', '');
			$this->RegisterAttributeString('Ahead_Price_Data', '');

			$this->RegisterPropertyInteger("HTML_FontSizeMinB", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxB", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefB", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontSizeMinH", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxH", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefH", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontSizeMinP", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxP", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefP", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontColorBars", self::HTML_Color_White);
			$this->RegisterPropertyInteger("HTML_FontColorHour", self::HTML_Color_White);
			
			$this->RegisterPropertyBoolean("HTML_FontColorHourDefaultSymcon", false);
			$this->RegisterPropertyInteger("HTML_FontColorHourDefault", self::HTML_Color_Black);

			$this->RegisterPropertyInteger("HTML_BGColorHour", self::HTML_Color_Grey);
			$this->RegisterPropertyInteger("HTML_BorderRadius", self::HTML_Default_PX);
			$this->RegisterPropertyInteger("HTML_Scale", self::HTML_Default_PX);

			$this->RegisterPropertyInteger("HTML_BGCstartG", self::HTML_Color_Mint);
			$this->RegisterPropertyInteger("HTML_BGCstopG", self::HTML_Color_Darkmint);
			$this->RegisterPropertyBoolean("HTML_MarkPriceLevel", false);
			
			$this->RegisterPropertyInteger("HTML_PriceLevelThick", self::HTML_Default_PX);
			$this->RegisterPropertyInteger("HTML_BGColorPriceVC", self::HTML_Color_Darkgreen);
			$this->RegisterPropertyInteger("HTML_BGColorPriceC", self::HTML_Color_Green);
			$this->RegisterPropertyInteger("HTML_BGColorPriceN", self::HTML_Color_Mint);
			$this->RegisterPropertyInteger("HTML_BGColorPriceE", self::HTML_Color_Orange);
			$this->RegisterPropertyInteger("HTML_BGColorPriceVE", self::HTML_Color_Red);
			$this->RegisterPropertyInteger("HTML_Default_HourAhead", self::HTML_Default_HourAhead);
			$this->RegisterPropertyInteger("HTML_Bar_Price_Round", self::HTML_Bar_Price_Round);
			$this->RegisterPropertyBoolean("HTML_Bar_Price_vis_ct", self::HTML_Bar_Price_vis_ct);
			
			$this->RegisterPropertyBoolean("HTML_Hour_WriteMode", self::HTML_Hour_WriteMode);


			$this->SetVisualizationType(1);

			//--- Register Timer
			$this->RegisterTimer("UpdateTimerPrice", 0, 'TIBV2_GetPriceData($_IPS[\'TARGET\']);');
			$this->RegisterTimer("UpdateTimerActPrice", 0, 'TIBV2_SetActualPrice($_IPS[\'TARGET\']);');

		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			if ($this->ReadPropertyString("Token") == ''){
				$this->SetStatus(201); // Kein Token
            	return false;
			}
			if ($this->ReadPropertyString("Token") != '' && $this->ReadPropertyString("Home_ID") == '0'){
				$this->SetStatus(202); // Kein Zuhause
				$this->GetHomesData();
            	return false;
			}

			$this->RegisterProfiles();
			$this->RegisterVariables();
			$this->SetValue('RT_enabled',$this->CheckRealtimeAvailable());
			$this->GetPriceData();
			$this->SetActualPrice();
			
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetStatus(102); // instanz aktiveren
			}
			else
			{
				$this->SetStatus(104); // instanz deaktiveren
			}
			// Tile Visu update
			//$this->UpdateVisualizationValue($this->GetFullUpdateMessage());
			$this->Reload();

		}
		
		public function GetPriceData()
		{
			if ($this->GetStatus() == 203)
			{
				$this->SetStatus(104);
			}
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { currentSubscription { priceInfo { today { total energy tax startsAt level } tomorrow { total energy tax startsAt level }}}}}}"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen

			$this->SendDebug("Price_Result", $result, 0);

			$this->ProcessPriceData($result);
			$this->SetUpdateTimerPrices();
			$this->Statistics(json_decode($this->PriceArray(), true));
			$this->Update_Ahead_Price_Data();

			//$this->UpdateVisualizationValue($this->GetFullUpdateMessage());

		}

		public function GetConsumptionHourlyLast(int $count)
		{
			return $this->GetConsumptionData('HOURLY', $count);
		}

		public function GetConsumptionDailyLast(int $count)
		{
			return $this->GetConsumptionData('DAILY', $count);
		}

		public function GetConsumptionWeekylLast(int $count)
		{
			return $this->GetConsumptionData('WEEKLY', $count);
		}

		public function GetConsumptionMonthlyLast(int $count)
		{
			return $this->GetConsumptionData('MONTHLY', $count);
		}

		public function GetConsumptionYearlyLast(int $count)
		{
			return $this->GetConsumptionData('ANNUAL', $count);
		}

		public function GetConsumptionHourlyFirst(int $count)
		{
			return $this->GetConsumptionData('HOURLY', $count, $first='first:');
		}

		public function GetConsumptionDailyFirst(int $count)
		{
			return $this->GetConsumptionData('DAILY', $count, $first='first:');
		}

		public function GetConsumptionWeekylFirst(int $count)
		{
			return $this->GetConsumptionData('WEEKLY', $count, $first='first:');
		}

		public function GetConsumptionMonthlyFirst(int $count)
		{
			return $this->GetConsumptionData('MONTHLY', $count, $first='first:');
		}

		public function GetConsumptionYearlyFirst(int $count)
		{
			return $this->GetConsumptionData('ANNUAL', $count, $first='first:');
		}

		public function SetActualPrice(){
			date_default_timezone_set('Europe/Berlin');
			if ($this->ReadAttributeString("Price_Array") == ''){
				$this->GetPriceData();
			}
			if ($this->ReadAttributeString("Price_Array") != ''){
				$prices = json_decode($this->ReadAttributeString("Price_Array"),true);

				
				$h = date('G');
				foreach ( $prices as $wa_price ){
					$hour = substr($wa_price["Ident"],9);
					$day  = substr($wa_price["Ident"],6,2);

					if ( $hour == $h && $day == 'T0'){
						$this->SetValue('act_price' , $wa_price["Price"]);	
						$PRICE_LVL = 0;

						switch($wa_price["Level"])
						{
							case "VERY_CHEAP":
								$PRICE_LVL = 1;
							break;
							case "CHEAP":
								$PRICE_LVL = 2;
							break;
							case "NORMAL":
								$PRICE_LVL = 3;
							break;
							case "EXPENSIVE":
								$PRICE_LVL = 4;
							break;
							case "VERY_EXPENSIVE":
								$PRICE_LVL = 5;
							break;
						}

						$this->SetValue('act_level', $PRICE_LVL );	
					}
				}
				$this->Update_Ahead_Price_Data();
				$this->SetUpdateTimerActualPrice();
				
				// Tile Visu update
				$this->UpdateVisualizationValue($this->GetFullUpdateMessage());
			}
		}

		public function GetConfigurationForm()
		{
			$jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);

			$result=$this->ReadAttributeString("Homes");
			$this->SendDebug("Form_homes", $result, 0);
			if ($result == '') return;
			$homes = json_decode($result, true);
			$value[] = ["caption"=> "Select Home", "value"=> "0" ];
			//$value ="";
			foreach ($homes["data"]["viewer"]["homes"] as $key => $home){
				if (empty($home["appNickname"]) )
					{	
						$caption = $home['address']['address1']; 
					}
					else
					{
						$caption = $home["appNickname"];
					}
				$value[] = ["caption"=> $caption, "value"=> $home["id"] ];
			}
			$jsonform["elements"][2]["options"] = $value;
			$jsonform["elements"][2]["visible"] = true;
			$jsonform["elements"][5]["items"][3]["visible"] = $this->ReadPropertyBoolean('HTML_FontColorHourDefaultSymcon');

			$jsonform["elements"][6]["items"][6]["visible"] = $this->ReadPropertyBoolean('HTML_MarkPriceLevel');
			//$jsonform["elements"][7]["items"][3] = $this->ReadPropertyBoolean('HTML_Hour_WriteMode');

			return json_encode($jsonform);
		}

		private function GetConsumptionData(string $timing, int $count, string $first='last:')
		{
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { consumption(resolution: '.$timing.','.$first.$count.') { nodes { from to cost unitPrice unitPriceVAT consumption consumptionUnit currency }}}}}"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen

			$this->SendDebug("Consumption_Result", $result, 0);
			//$this->process_consumption_data($result, $timing);
			return $result;
		}

		private function ProcessConsumptionData(string $result, string $timing)
		{
			$log_consum = '';
			$log_price = '';
			$log_costs = '';
			$con = json_decode($result, true);

			switch ($timing){
				case "HOURLY":	
					$log_consum = 'hourly_consumption';	
					$log_price =  'hourly_price';	
					$log_costs =  'hourly_costs';	
				case "DAILY":	
					$log_consum = 'daily_consumption';
					$log_price = 'daily_price';
					$log_costs = 'daily_costs';	
				case "WEEKLY":	
					$log_consum = 'weekly_consumption';
					$log_price = 'weekly_price';
					$log_costs = 'weekly_costs';	
				case "MONTHLY":	
					$log_consum = 'monthly_consumption';
					$log_price = 'monthly_price';
					$log_costs = 'monthly_costs';	
				case "ANNUAL":	
					$log_consum = 'annual_consumption';
					$log_price = 'annual_price';
					$log_costs = 'annual_costs';	
			}

			foreach ($con["data"]["viewer"]["home"]["consumption"]["nodes"] AS $key => $wa_con) {
				
				$start = strtotime($wa_con["from"]);
				$end = strtotime($wa_con["from"]) - 1; 
				// Consumption Update
					AC_DeleteVariableData($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum), $start, $end);
					$last_log= AC_GetLoggedValues($this->ReadAttributeInteger("ar_handler"),$this->GetIDForIdent($log_consum),$start - 1, $start -1, 1 )[0]['Value'];
					if ($last_log != ''){ }
				AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum), [[ 'TimeStamp' => $end, 'Value' => $wa_con["consumption"] ]]);	
			}
				AC_ReAggregateVariable($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum));	

		}

		private function ProcessPriceData(string $result)
		{
			$t1 = false;
			$result_array = [];
			$prices = json_decode($result, true);

			// check if currentSubscription is nul, in this case we dont have a contract and dont get price infos
			// wrong if ($prices["data"]["viewer"]["home"]["currentSubscription"] == false)
			if (empty($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["today"]))
				{
					$this->SetStatus(203);
					return;
				}
		
			foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["today"] AS $key => $wa_price) {
				
				$var = 'PT60M_T0_'.$key;
				$this->SetPriceVariables($var, $wa_price);
				$result_array[] = [ 'Ident' => $var,
									'Price' => $wa_price['total'] * 100,
									'Level' => $wa_price['level'],
									'start' => strtotime($wa_price['startsAt']),
							 		'end' 	=> strtotime("+1 hour", strtotime($wa_price['startsAt'])) ];

			}
			foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["tomorrow"] AS $key => $wa_price) {
				
				$t1 = true;
				$var = 'PT60M_T1_'.$key;
				$this->SetPriceVariables($var, $wa_price);
				$result_array[] = [ 'Ident' => $var,
									'Price' => $wa_price['total'] * 100,
									'Level' => $wa_price['level'],
									'start' => strtotime($wa_price['startsAt']),
									'end' 	=> strtotime("+1 hour", strtotime($wa_price['startsAt']))];

			}

			if (!$t1){
				for ($i = 0; $i <= 23; $i++) {
					$var = 'PT60M_T1_'.$i;
				$this->SetPriceVariablesZero($var);
				$result_array[] = [ 'Ident' => $var,
									'Price' => 0,
									'Level'	=> ''];
				}
				$this->WriteAttributeBoolean('EEX_Received', false);
			}
			else{
				$this->WriteAttributeBoolean('EEX_Received', true);
			}
			
       		$this->WriteAttributeString("Price_Array", json_encode($result_array));
	
			//update tile Visu
			$this->Update_Ahead_Price_Data();
			//$this->UpdateVisualizationValue($this->GetFullUpdateMessage());

			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->LogAheadPrices($result_array);
			}


		}

		private function Update_Ahead_Price_Data()
		{
			$this->SendDebug(__FUNCTION__, $this->ReadAttributeString("Price_Array"), 0);

			if ($this->ReadAttributeString("Price_Array") != '')
			{
				$Ahead_Price_Data = [];
				$h = date('G');
				$lastHour = "";
				$AVGPrice = array();
				$dateIndex = 0;
				foreach (json_decode($this->ReadAttributeString('Price_Array'),true) as $data => $value)
				{

					if (empty($value['start']))
					{
						$valueStart = strtotime("+1 hour",$lastHour); 
						$lastHour = $valueStart; 
					}
					else
					{
						$valueStart = $value['start']; 
						$lastHour = $valueStart;
					}

					if (empty($value['end']))
					{
						$valueEnd = strtotime("+1 hour",$valueStart); 	
					}
					else
					{
						$valueEnd = $value['end'];
					}

					if (empty($value['Price']))
					{
						$valuePrice = 0;
					}
					else
					{
						$valuePrice = $value['Price'];
						if ($data >= $h)
						{
							if ($dateIndex >= self::HTML_Max_HourAhead){ break; }
							$AVGPrice[] = $valuePrice;
							$dateIndex++;
						}
					}
					if (empty($value['Level']))					
					{
						$valueLevel = "";
					}
					else
					{
						$valueLevel = $value['Level'];
					}

					if ($data >= $h)
					{
						$Ahead_Price_Data[] = [ 'start' => $valueStart,
												'end'   => $valueEnd,
												'price' => round($valuePrice,2),
												'level' => $valueLevel
											];
					}
					
				}
	
				$this->WriteAttributeString('AVGPrice',json_encode($AVGPrice));
				$Ahead_Price_Data = json_encode($Ahead_Price_Data);
				$this->SendDebug(__FUNCTION__, json_encode($Ahead_Price_Data), 0);

				$this->WriteAttributeString('Ahead_Price_Data', $Ahead_Price_Data);
				if ($this->ReadPropertyBoolean('Ahead_Price_Data_bool')){
					$this->SetValue("Ahead_Price_Data", $Ahead_Price_Data);
				}
				//$this->UpdateVisualizationValue($this->GetFullUpdateMessage());
			}
		}

		private function LogAheadPrices($result_array)
		{
			date_default_timezone_set('Europe/Berlin');
			$start = mktime(0, 0, 0, intval( date("m") ) , intval(date("d")-2), intval(date("Y")));
			$end = mktime(23, 59, 59, intval( date("m") ) , intval(date("d")-1), intval(date("Y")));

			AC_DeleteVariableData($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), $start, $end);

			foreach ( $result_array as $Pos => $res ){
				if ( substr($res["Ident"],7,1) == 0 ) {
					$hour = intval(substr($res["Ident"],9));
					AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime($hour, 00, 01, intval( date("m") ) , intval(date("d")-2), intval(date("Y"))), 'Value' => $res["Price"] ]]);
				}
				elseif ( substr($res["Ident"],7,1) == 1 ){
					$hour = intval(substr($res["Ident"],9));
					AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime($hour, 00, 01, intval( date("m") ) , intval(date("d")-1), intval(date("Y"))), 'Value' => $res["Price"] ]]);
				}
			}
			$count = count($result_array);
			$this->SendDebug('Result_array', $count, 0);
			if ($count <= self::HTML_Max_HourAhead){
				AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime(00, 00, 01, intval( date("m") ) , intval(date("d")-1), intval(date("Y"))), 'Value' => 0 ]]);
			}
			AC_ReAggregateVariable($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"));
		}

		private function SetLogging()
		{
			$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';  //ARchive Handler ermitteln
			$ar = IPS_GetInstanceListByModuleID($archive_handler);
			$ar_id = intval($ar[0]);
			$this->WriteAttributeInteger("ar_handler", $ar_id);

			$status = AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("Ahead_Price"));
			if ($status == false){
				AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("Ahead_Price"), true );
			}
			unset($status);
			
			$status = AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("act_price"));
			if ($status == false){
				AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("act_price"), true );
			}
			unset($status);
			
			$this->CreateAheadChart();
		}
		
		private function CreateAheadChart()
		{
			if (!@$this->GetIDForIdent('TIBV2_Day_Ahead_Chart')){
				$var = $this->GetIDForIdent('Ahead_Price');
				$id = IPS_CreateMedia(4);
				IPS_SetParent($id,  $this->InstanceID);
				$payload = '{"datasets":[{"variableID":'.$var.',"fillColor":"#669c35","strokeColor":"#77bb41","timeOffset":-2,"visible":true,"title":"Preis Heute","type":"bar","side":"left"},{"variableID":'.$var.',"fillColor":"#f2f7b7","strokeColor":"#f2f7b7","timeOffset":-1,"visible":true,"title":"Preis Morgen","type":"bar","side":"left"}]}';
				IPS_SetMediaFile($id,IPS_GetKernelDir().join(DIRECTORY_SEPARATOR, array("media", $id.".chart")),0);
				IPS_SetMediaContent($id, base64_encode($payload));
				IPS_SetName($id,'Day Ahead Chart');	
				IPS_SetIdent($id, 'TIBV2_Day_Ahead_Chart') ;
				IPS_SetPosition($id, 200);
			}
		}

		private function SetPriceVariables(string $var, array $wa_price)
		{	
			if ($this->ReadPropertyBoolean('Price_Variables')){
				$this->setvalue($var, $wa_price['total'] *100);
			}
		}

		private function SetPriceVariablesZero(string $var)
		{	
			if ($this->ReadPropertyBoolean('Price_Variables')){
				$this->setvalue($var, 0 );
			}
		}

		private function SetUpdateTimerPrices()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <13){
				$time_new = mktime(13, 0, 0, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				if (!$this->ReadAttributeBoolean('EEX_Received')){
					$time_new = time() + 300;								// Alle 5 Minuten abholen bis T1 Wert geliefert wird.
				}
				else{
					$time_new = mktime(0, 0, 5, intval( date("m") ) , intval(date("d") + 1), intval(date("Y")));
				}
			}
			$timer_new = $time_new - time();
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetTimerInterval("UpdateTimerPrice", $timer_new * 1000);
			}
			else
			{
				$this->SetTimerInterval("UpdateTimerPrice", 0);
			}
			$this->SendDebug('Price Timer - Rundate', date('c', $time_new),0);
			$this->SendDebug('Price Timer - Run in sec', $timer_new ,0);

		}

		private function SetUpdateTimerActualPrice()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <23){
				$time_new = mktime($h+1, 0, 01, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				$time_new = mktime(0, 0, 10, intval( date("m") ) , intval(date("d")+1), intval(date("Y")));
			}
			$timer_new = $time_new - time();
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetTimerInterval("UpdateTimerActPrice", $timer_new * 1000);
			}
			else
			{
				$this->SetTimerInterval("UpdateTimerActPrice", 0);
			}
			$this->SendDebug('Act-Price Timer - Rundate', date('c', $time_new),0);
			$this->SendDebug('Act-Price Timer - Run in sec', $timer_new ,0);
		}

		private function CalcNewDay()
		{
			date_default_timezone_set('Europe/Berlin');
			$date_new = mktime(0, 0, 01, intval( date("m") ) , intval(date("d")+1), intval(date("Y")));
			$act_date = time();
			return $date_new - $act_date;
		}

		private function CalcNewHour()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <23){
				$h = date('G') +1;
				$date_new = mktime($h, 0, 01, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				$date_new = time() + 3600;
			}
			$act_date = time();
			return $date_new - $act_date;
		}
		
		private function RegisterVariables()
		{
			if ($this->ReadPropertyBoolean('Price_Variables')){
				for ($i = 0; $i <= 23; $i++) {
					$this->RegisterVariableFloat("PT60M_T0_" . $i, $this->Translate('Today')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), "Tibber.price.cent", 20 + $i);
				}
				for ($i = 0; $i <= 23; $i++) {
					$this->RegisterVariableFloat("PT60M_T1_" . $i, $this->Translate('Tomorrow')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), "Tibber.price.cent", 50 + $i);
				}
			} 
			else
			{
				for ($i = 0; $i <= 23; $i++) {

					if (@$this->GetIDForIdent("PT60M_T0_" . $i))
					{
						$this->UnregisterVariable("PT60M_T0_" . $i);
					}
				}
				for ($i = 0; $i <= 23; $i++) {
					if (@$this->GetIDForIdent("PT60M_T1_" . $i))
					{
						$this->UnregisterVariable("PT60M_T1_" . $i);
					}
				}
			}
			//$this->RegisterVariableFloat("hourly_consumption", 'Stündlicher Verbrauch', "", 0);
			$this->RegisterVariableFloat("act_price", $this->Translate('actual price'), 'Tibber.price.cent', 0);
			$this->RegisterVariableInteger("act_level", $this->Translate('actual price level'), 'Tibber.price.level', 0);
			$this->RegisterVariableBoolean("RT_enabled", $this->Translate('realtime available'), '', 0);

			if ($this->ReadPropertyBoolean('Ahead_Price_Data_bool') == true){
				$this->RegisterVariableString("Ahead_Price_Data", $this->Translate("Ahead price data variable for energy optimizer"), "~TextBox", 0);
			}
			else
			{
				$this->UnregisterVariable('Ahead_Price_Data');
			}

			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->RegisterVariableFloat("Ahead_Price", $this->Translate('day ahead price helper variable'), 'Tibber.price.cent', 0);
				$this->SetLogging();
			}

			// Statistic
			if ($this->ReadPropertyBoolean('Statistics')){

				$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';  //ARchive Handler ermitteln
				$ar = IPS_GetInstanceListByModuleID($archive_handler);
				$ar_id = intval($ar[0]);

				//tomorrow
				$this->RegisterVariableFloat("minprice", $this->Translate('minimum Price for tomorrow'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minprice"), true );}

				$this->RegisterVariableFloat("maxprice", $this->Translate('maximum Price for tomorrow'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("maxprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("maxprice"), true );}

				$this->RegisterVariableFloat("minmaxprice", $this->Translate('minimum/maximum Price range for tomorrow'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minmaxprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minmaxprice"), true );}
				
				$this->RegisterVariableInteger("lowtime", $this->Translate('lowest price at this point in time for tomorrow'), 'Tibber.price.hour', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("lowtime")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("lowtime"), true );}
				
				$this->RegisterVariableInteger("hightime", $this->Translate('highest price at this point in time for tomorrow'), 'Tibber.price.hour', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("hightime")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("hightime"), true );}
				
				// today
				$this->RegisterVariableFloat("minprice_today", $this->Translate('minimum Price for today'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minprice_today")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minprice_today"), true );}

				$this->RegisterVariableFloat("maxprice_today", $this->Translate('maximum Price for today'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("maxprice_today")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("maxprice_today"), true );}

				$this->RegisterVariableFloat("minmaxprice_today", $this->Translate('minimum/maximum Price range for today'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minmaxprice_today")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minmaxprice_today"), true );}
				
				$this->RegisterVariableInteger("lowtime_today", $this->Translate('lowest price at this point in time for today'), 'Tibber.price.hour', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("lowtime_today")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("lowtime_today"), true );}
				
				$this->RegisterVariableInteger("hightime_today", $this->Translate('highest price at this point in time for today'), 'Tibber.price.hour', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("hightime_today")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("hightime_today"), true );}

				// counter
				$this->RegisterVariableInteger("no_level1", $this->Translate('quantity of very cheapest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level1")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level1"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level1"), 1);}

				$this->RegisterVariableInteger("no_level2", $this->Translate('quantity of cheapest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level2")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level2"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level2"), 1);}

				$this->RegisterVariableInteger("no_level3", $this->Translate('quantity of normal price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level3")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level3"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level3"), 1);}

				$this->RegisterVariableInteger("no_level4", $this->Translate('quantity of highest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level4")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level4"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level4"), 1);}

				$this->RegisterVariableInteger("no_level5", $this->Translate('quantity of very highest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level5")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level5"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level5"), 1);}

			}
			else
			{
				$this->UnregisterVariable("minprice");
				$this->UnregisterVariable("maxprice");
				$this->UnregisterVariable("minmaxprice");
				$this->UnregisterVariable("lowtime");
				$this->UnregisterVariable("hightime");
				$this->UnregisterVariable("minprice_today");
				$this->UnregisterVariable("maxprice_today");
				$this->UnregisterVariable("minmaxprice_today");
				$this->UnregisterVariable("lowtime_today");
				$this->UnregisterVariable("hightime_today");
				$this->UnregisterVariable("no_level1");
				$this->UnregisterVariable("no_level2");
				$this->UnregisterVariable("no_level3");
				$this->UnregisterVariable("no_level4");
				$this->UnregisterVariable("no_level5");
			}
			
		}

		private function RegisterProfiles()
		{
			if (!IPS_VariableProfileExists('Tibber.price.cent')) {
				IPS_CreateVariableProfile('Tibber.price.cent', 2);
				IPS_SetVariableProfileIcon('Tibber.price.cent', 'Euro');
				IPS_SetVariableProfileDigits("Tibber.price.cent", 2);
				IPS_SetVariableProfileText("Tibber.price.cent", "", " Cent");
			}
			
			if (!IPS_VariableProfileExists('Tibber.price.level')) {
				IPS_CreateVariableProfile('Tibber.price.level', 1);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 0, '-', '', 0xFFFFFF);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 1, $this->Translate('very cheap'), '', 0x00FF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 2, $this->Translate('cheap'), '', 0x008000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 3, $this->Translate('normal'), '', 0xFFFF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 4, $this->Translate('expensive'), '', 0xFF8000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 5, $this->Translate('very expensive'), '', 0xFF0000);
			}

			if (!IPS_VariableProfileExists('Tibber.price.hour')) {
				IPS_CreateVariableProfile('Tibber.price.hour', 1);
				IPS_SetVariableProfileText("Tibber.price.hour", "", $this->Translate(' o clock'));
				IPS_SetVariableProfileValues("Tibber.price.hour", 0, 23, 1);

			}
			
		}

		public function RequestAction($Ident, $Value)
		{
			switch ($Ident) {
				case "GetHomesData":
					$this->GetHomesData();
				break;
				case "CheckRealtimeEnabled":
					$this->CheckRealtimeAvailable();
				break;
				case "ShowPriceLevelEnhanced":
					$this->UpdateFormField("ShowPriceLevelEnhanced", "visible", $Value);
				break;
				case "ResetHTML":
					$this->ResetHTML();
				break;
				case "ShowDefaultFontColorHour":
					$this->UpdateFormField("HTML_FontColorHourDefault", "visible", $Value);
				break;
				case "reload":
					$this->Reload();
				break;
			}
		}

		private function Statistics(array $Data)
		{
			if ($this->ReadPropertyBoolean('Statistics'))
			{
				$noon = false;
				date_default_timezone_set('Europe/Berlin');
				$h = date('G');
				if ($h >=13)
				{ 
					$noon = true;
				}
		
				if ($noon)
				{
					// Initialisiere der Variablen
					$minPrice = PHP_INT_MAX;
					$minPriceIdent = '';
					$maxPrice = PHP_INT_MIN;
					$maxPriceIdent = '';
					$levelCount = array('VERY_CHEAP'=>0,'CHEAP'=>0,'NORMAL'=>0,'EXPENSIVE'=>0,'VERY_EXPENSIVE'=>0);

					//durchlaufe das Array, um den geringste und höchsten Preis inkl. Stunde (Ident) für morgen zu finden
					for ($i = 24; $i <= 47; $i++)
					{
						$currentPrice = $Data[$i]['Price'];
						//geringster Preis
						if ($currentPrice < $minPrice)
						{
							$minPrice = $currentPrice;
							$minPriceIdent = $Data[$i]['Ident'];
						}
						//höchster Preis
						if ($currentPrice > $maxPrice)
						{
							$maxPrice = $currentPrice;
							$maxPriceIdent = $Data[$i]['Ident'];
						}
					}

						for ($i = 24; $i <= 47; $i++)
						{
							$level = $Data[$i]['Level'];
							if (!empty($level))
							{
								$levelCount[$level]++;
							}
						}
					//gib den geringsten und höchsten Preis aus
					$this->SetValue('minprice', $minPrice);

					$minTime=intval(substr($minPriceIdent, 9)); //Uhrzeit (Stunde), in welcher der niedrigste Preis gilt
					$this->SetValue('lowtime', $minTime);
					
					$this->SetValue('maxprice', $maxPrice);
					$maxTime=intval(substr($maxPriceIdent, 9)); //Uhrzeit (Stunde), in welcher der hächste Preis gilt
					$this->SetValue('hightime', $maxTime);
					$Spanne=$maxPrice-$minPrice;  //Preisspanne zwischen min und max
					$this->SetValue('minmaxprice', $Spanne);
					
					//Zuordnung der Preislevel zu Variablen
					//Anzahl der Preislevel am Folgetag
					$this->SetValue('no_level1', $levelCount['VERY_CHEAP']);
					$this->SetValue('no_level2', $levelCount['CHEAP']);
					$this->SetValue('no_level3', $levelCount['NORMAL']);
					$this->SetValue('no_level4', $levelCount['EXPENSIVE']);
					$this->SetValue('no_level5', $levelCount['VERY_EXPENSIVE']);
				}
				else
				{
					// Initialisiere der Variablen
					$minPrice_today = PHP_INT_MAX;
					$minPriceIdent_today = '';
					$maxPrice_today = PHP_INT_MIN;
					$maxPriceIdent_today = '';

					//durchlaufe das Array, um den geringste und höchsten Preis inkl. Stunde (Ident) für morgen zu finden
					for ($i = 0; $i <= 23; $i++)
					{
						$currentPrice_today = $Data[$i]['Price'];
						//geringster Preis
						if ($currentPrice_today < $minPrice_today)
						{
							$minPrice_today = $currentPrice_today;
							$minPriceIdent_today = $Data[$i]['Ident'];
						}
						//höchster Preis
						if ($currentPrice_today > $maxPrice_today)
						{
							$maxPrice_today = $currentPrice_today;
							$maxPriceIdent_today = $Data[$i]['Ident'];
						}
						//gib den geringsten und höchsten Preis aus
						$this->SetValue('minprice_today', $minPrice_today);

						$minTime_today=intval(substr($minPriceIdent_today, 9)); //Uhrzeit (Stunde), in welcher der niedrigste Preis gilt
						$this->SetValue('lowtime_today', $minTime_today);
						
						$this->SetValue('maxprice_today', $maxPrice_today);
						$maxTime_today=intval(substr($maxPriceIdent_today, 9)); //Uhrzeit (Stunde), in welcher der hächste Preis gilt
						$this->SetValue('hightime_today', $maxTime_today);
						$Spanne_today=$maxPrice_today-$minPrice_today;  //Preisspanne zwischen min und max
						$this->SetValue('minmaxprice_today', $Spanne_today);
					}
				}
			}
		}

		public function PriceArray()
		{
			return $this->ReadAttributeString('Price_Array');
		}

		public function GetVisualizationTile()
        {
			$initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

            // Add static HTML content from file to make editing easier
            $module = file_get_contents(__DIR__ . '/module.html');

			// Return everything to render our fancy tile!
            return $module . $initialHandling;
        }	

		private function GetFullUpdateMessage()
		{
			$result = [];

			if (!empty($this->ReadAttributeString("AVGPrice")))
			{
				$AVGPriceVal			= json_decode($this->ReadAttributeString("AVGPrice"),true);
				$result['price_avg'] 	= round(array_sum($AVGPriceVal)/count($AVGPriceVal),2);
				$result['price_min'] 	= round(min($AVGPriceVal),2);
				$result['price_max'] 	= round(max($AVGPriceVal),2);
				$result['price_cur'] 	= $AVGPriceVal[0];		
			}
			else
			{
				$result['NoData'] = $this->Translate('no data available, please check log file for error messages.'); 
			}
			$result['FontSizeBars']  	= $this->ReadPropertyInteger("HTML_FontSizeMinB")."px, ".$this->ReadPropertyInteger("HTML_FontSizeDefB")."vw, ".$this->ReadPropertyInteger("HTML_FontSizeMaxB")."px";
			$result['FontSizeHours']  	= $this->ReadPropertyInteger("HTML_FontSizeMinH")."px, ".$this->ReadPropertyInteger("HTML_FontSizeDefH")."vw, ".$this->ReadPropertyInteger("HTML_FontSizeMaxH")."px";
			$result['FontSizePrices']  	= $this->ReadPropertyInteger("HTML_FontSizeMinP")."px, ".$this->ReadPropertyInteger("HTML_FontSizeDefP")."vw, ".$this->ReadPropertyInteger("HTML_FontSizeMaxP")."px";

			$result['FCBars'] 	 		= sprintf('%06X', $this->ReadPropertyInteger("HTML_FontColorBars"));
			$result['FCHour'] 	 		= sprintf('%06X', $this->ReadPropertyInteger("HTML_FontColorHour"));

			if ($this->ReadPropertyBoolean("HTML_FontColorHourDefaultSymcon")){
				$result['FCHourDefault'] 	 		= sprintf('%06X', $this->ReadPropertyInteger("HTML_FontColorHourDefault"));
			}
			else
			{
				$result['FCHourDefault'] 	 		= false;
			}
			
			$result['BGCHour'] 			= sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorHour"));
			$result['BorderRadius']		= $this->ReadPropertyInteger("HTML_BorderRadius");
			$result['Scale']			= $this->ReadPropertyInteger("HTML_Scale");
			$result['Gradient']			= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstartG")).", #".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstopG"));
			$result['MarkPriceLevel']	= $this->ReadPropertyBoolean("HTML_MarkPriceLevel");
						
			$result['BGCPriceVC']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceVC"));
			$result['BGCPriceC']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceC"));
			$result['BGCPriceN']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceN"));
			$result['BGCPriceE']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceE"));
			$result['BGCPriceVE']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceVE"));
			$result['PriceLevelThickness']			= $this->ReadPropertyInteger("HTML_PriceLevelThick");
			$result['HourAhead']					= min ($this->ReadPropertyInteger("HTML_Default_HourAhead"), $this->hoursUntilTomorrowMidnight());

			$result['bar_price_round']				= $this->ReadPropertyInteger("HTML_Bar_Price_Round");
			$result['bar_price_vis_ct']				= $this->ReadPropertyBoolean("HTML_Bar_Price_vis_ct");

			$result['hour_write_mode']				= $this->ReadPropertyBoolean("HTML_Hour_WriteMode");;

			$result['Ahead_Price_Data'] = json_decode($this->ReadAttributeString('Ahead_Price_Data'),true);
            //$result['Ahead_Price_Data'] = json_decode($this->GetValue("Ahead_Price_Data"),true);

			return json_encode($result) ;
		}

        private function hoursUntilTomorrowMidnight(): int
   		{
			// Beginn der aktuellen Stunde
			$currentHourStart = (new DateTime())->setTime((int)date('H'), 0, 0);

			// übermorgen Mitternacht
			$overTomorrowMidnight = new DateTime('+2 days midnight');

			// Berechnung der Stunden bis übermorgen Mitternacht
			$interval = $currentHourStart->diff($overTomorrowMidnight);
			return ($interval->days * 24) + $interval->h;
    	}
		
		public function GetFullUpdateMessageMANU()
		{
			//funktion um die Kachelvisu besser testen zu können.
			$result[] = $this->GetFullUpdateMessage();
            $result['Ahead_Price_Data'] = json_decode($this->GetValue("Ahead_Price_Data"),true);

			$this->UpdateVisualizationValue(json_encode($result));
			$this->SendDebug(__FUNCTION__,'Update Manu: '.json_encode($result),0);
			return ;
		}

		// just reload location
		public function Reload()
		{
			//funktion um die Kachelvisu besser testen zu können.
            $result['reload'] = true;

			$this->UpdateVisualizationValue(json_encode($result));
			$this->SendDebug(__FUNCTION__,'Reload Tile: '.json_encode($result),0);
			return ;
		}
		//allow to reset all HTML Variables to default
		private function ResetHTML()
		{
			$defaults = [ 
				'HTML_FontSizeMinB'=> self::HTML_FontSizeMin,
				'HTML_FontSizeMaxB'=> self::HTML_FontSizeMax,
				'HTML_FontSizeDefB'=> self::HTML_FontSizeDef,
				'HTML_FontSizeMinH'=> self::HTML_FontSizeMin,
				'HTML_FontSizeMaxH'=> self::HTML_FontSizeMax,
				'HTML_FontSizeDefH'=> self::HTML_FontSizeDef,
				'HTML_FontSizeMinP'=> self::HTML_FontSizeMin,
				'HTML_FontSizeMaxP'=> self::HTML_FontSizeMax,
				'HTML_FontSizeDefP'=> self::HTML_FontSizeDef,
				'HTML_FontColorBars'=> self::HTML_Color_White,
				'HTML_FontColorHour'=> self::HTML_Color_White,
				'HTML_FontColorHourDefault'=> self::HTML_Color_Black,
				'HTML_BGColorHour'=> self::HTML_Color_Grey,
				'HTML_BorderRadius'=> self::HTML_Default_PX,
				'HTML_Scale'=> self::HTML_Default_PX,
				'HTML_BGCstartG'=> self::HTML_Color_Mint,
				'HTML_BGCstopG'=> self::HTML_Color_Darkmint,
				'HTML_MarkPriceLevel'=> false,
				'HTML_PriceLevelThick'=> self::HTML_Default_PX,
				'HTML_BGColorPriceVC'=> self::HTML_Color_Darkgreen,
				'HTML_BGColorPriceC'=> self::HTML_Color_Green,
				'HTML_BGColorPriceN'=> self::HTML_Color_Mint,
				'HTML_BGColorPriceE'=> self::HTML_Color_Orange,
				'HTML_BGColorPriceVE'=> self::HTML_Color_Red,
				'HTML_Default_HourAhead'=> self::HTML_Default_HourAhead,
				'HTML_Bar_Price_Round'=> self::HTML_Bar_Price_Round,
				'HTML_Bar_Price_vis_ct'=> self::HTML_Bar_Price_vis_ct,
				'HTML_Hour_WriteMode'=> self::HTML_Hour_WriteMode		
			
			];
			
			foreach ($defaults as $data => $value)
			{
				$this->UpdateFormField($data, 'value', $value); 
				$this->SendDebug(__FUNCTION__,'set '.$data.' to default: '.$value,0);
			}
		}
	}