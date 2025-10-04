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
			// Backend-Demo-Schalter
			$this->RegisterPropertyBoolean("DemoMode", false);
			$this->RegisterPropertyString("Token", '');
			$this->RegisterPropertyString("Api", 'https://api.tibber.com/v1-beta/gql');
			$this->RegisterPropertyString("Home_ID",'0');
			$this->RegisterPropertyBoolean("Price_log", false);
			$this->RegisterPropertyBoolean("Price_Variables", false);
			$this->RegisterPropertyBoolean("Price_Variables_15m", false);
			// Globaler Schalter: 15-Minuten-Preise aktivieren
			$this->RegisterPropertyBoolean("Enable_15m", false);

			$this->RegisterPropertyBoolean("Statistics", false);
			$this->RegisterPropertyBoolean("Ahead_Price_Data_bool", false);
			
			$this->RegisterAttributeString("Homes", "");
			$this->RegisterAttributeString("Price_Array", '');
			$this->RegisterAttributeInteger("ar_handler", 0);
			$this->RegisterAttributeBoolean("EEX_Received", false);
			$this->RegisterAttributeString('AVGPrice', '');
			$this->RegisterAttributeString('Ahead_Price_Data', '');
			// holds raw quarter-hourly price entries from API when available
			$this->RegisterAttributeString('Price_Array_15m', '');

			$this->RegisterPropertyInteger("HTML_FontSizeMinB", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxB", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefB", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontSizeMinH", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxH", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefH", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontSizeMinP", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxP", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefP", self::HTML_FontSizeDef);

			// separate font-size for price scale (overlay grid labels)
			$this->RegisterPropertyInteger("HTML_FontSizeMinS", self::HTML_FontSizeMin);
			$this->RegisterPropertyInteger("HTML_FontSizeMaxS", self::HTML_FontSizeMax);
			$this->RegisterPropertyInteger("HTML_FontSizeDefS", self::HTML_FontSizeDef);

			$this->RegisterPropertyInteger("HTML_FontColorBars", self::HTML_Color_White);
			$this->RegisterPropertyInteger("HTML_FontColorHour", self::HTML_Color_White);
			
			$this->RegisterPropertyBoolean("HTML_FontColorHourDefaultSymcon", false);
			$this->RegisterPropertyInteger("HTML_FontColorHourDefault", self::HTML_Color_Black);

			$this->RegisterPropertyInteger("HTML_BGColorHour", self::HTML_Color_Grey);
			$this->RegisterPropertyInteger("HTML_BorderRadius", self::HTML_Default_PX);
			$this->RegisterPropertyInteger("HTML_Scale", 0);

			// Toggle for OKLCH gradient optimization in frontend
			$this->RegisterPropertyBoolean("HTML_ColorGradientOptimization", false);

			$this->RegisterPropertyInteger("HTML_BGCstartG", self::HTML_Color_Mint);
			$this->RegisterPropertyInteger("HTML_BGCstopG", self::HTML_Color_Darkmint);
			// Gradient-Farben für den aktuellen Balken
			$this->RegisterPropertyInteger("HTML_BGCstartG_Current", self::HTML_Color_Orange);
			$this->RegisterPropertyInteger("HTML_BGCstopG_Current", self::HTML_Color_Red);
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
			$this->RegisterPropertyBoolean("HTML_Bar_Show_Prices", true);
			$this->RegisterPropertyBoolean("HTML_Show_Grid", false);
			// Only render bars that have real price info (no placeholders)
			$this->RegisterPropertyBoolean("HTML_OnlyPriceBars", false);
			
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
			$this->UpdateVisualizationValue($this->GetFullUpdateMessage());
			$this->Reload();

		}
		
		public function GetPriceData()
		{
			if ($this->GetStatus() == 203)
			{
				$this->SetStatus(104);
			}
			// Build Request Data (Resolution abhängig vom globalen 15m-Schalter)
			$resolution = $this->ReadPropertyBoolean('Enable_15m') ? 'QUARTER_HOURLY' : 'HOURLY';
			$homeId = $this->ReadPropertyString('Home_ID');
			$query = 'query { viewer { home(id: "' . $homeId . '") { currentSubscription { priceInfo(resolution: ' . $resolution . ') { today { total energy tax startsAt level } tomorrow { total energy tax startsAt level } } } } } }';
			$this->SendDebug('GraphQL_Query', $query, 0);
			$request = json_encode([ 'query' => $query ]);
			$result = $this->CallTibber($request);
			// Fallback: falls 15m-Auflösung nicht unterstützt wird oder Query fehlschlägt, erneut mit HOURLY versuchen
			if (!$result && $resolution === 'QUARTER_HOURLY') {
				$this->SendDebug(__FUNCTION__, 'Quarter-hourly failed, falling back to HOURLY', 0);
				$resolutionFB = 'HOURLY';
				$query = 'query { viewer { home(id: "' . $homeId . '") { currentSubscription { priceInfo(resolution: ' . $resolutionFB . ') { today { total energy tax startsAt level } tomorrow { total energy tax startsAt level } } } } } }';
				$this->SendDebug('GraphQL_Query', $query, 0);
				$request = json_encode([ 'query' => $query ]);
				$result = $this->CallTibber($request);
			}
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
				
				$h = intval(date('G'));
				$minNow = intval(date('i'));
				$segNow = intval(floor($minNow / 15)); // 0..3
				$usedQuarter = false;
				// Prefer true quarter-hourly price if available and 15m mode is enabled
				if ($this->ReadPropertyBoolean('Enable_15m')) {
					$rawStr = $this->ReadAttributeString('Price_Array_15m');
					if (!empty($rawStr)) {
						$raw = json_decode($rawStr, true);
						if (is_array($raw) && count($raw) > 0) {
							$nowSec = time();
							foreach ($raw as $row) {
								$st = isset($row['start']) ? intval($row['start']) : 0;
								$en = isset($row['end']) ? intval($row['end']) : ($st + 900);
								if ($nowSec >= $st && $nowSec < $en) {
									$p = isset($row['Price']) ? round(floatval($row['Price']), 2) : 0.0;
									$lvlTxt = $row['Level'] ?? '';
									$this->SetValue('act_price', $p);
									$PRICE_LVL = 0;
									switch($lvlTxt)
									{
										case "VERY_CHEAP":   $PRICE_LVL = 1; break;
										case "CHEAP":        $PRICE_LVL = 2; break;
										case "NORMAL":       $PRICE_LVL = 3; break;
										case "EXPENSIVE":    $PRICE_LVL = 4; break;
										case "VERY_EXPENSIVE": $PRICE_LVL = 5; break;
									}
									$this->SetValue('act_level', $PRICE_LVL);
									$usedQuarter = true;
									break;
								}
							}
						}
					}
				}
				// Fallback to hourly logic from Price_Array
				if (!$usedQuarter) {
					foreach ($prices as $wa_price){
						$ident = isset($wa_price["Ident"]) ? $wa_price["Ident"] : '';
						$day   = substr($ident,6,2); // 'T0' / 'T1'
						if ($day !== 'T0') { continue; }
						$PRICE_LVL = 0;
						if (strpos($ident, 'PT60M_') === 0){
							$hourVal = intval(substr($ident,9));
							if ($hourVal !== $h) { continue; }
						}
						elseif (strpos($ident, 'PT15M_') === 0){
							$idxQ = intval(substr($ident,9)); // 0..95
							$hourVal = intdiv($idxQ, 4);
							$segVal  = $idxQ % 4;
							if (!($hourVal === $h && $segVal === $segNow)) { continue; }
						}
						else { continue; }
						$this->SetValue('act_price' , $wa_price["Price"]);
						switch($wa_price["Level"])
						{
							case "VERY_CHEAP":   $PRICE_LVL = 1; break;
							case "CHEAP":        $PRICE_LVL = 2; break;
							case "NORMAL":       $PRICE_LVL = 3; break;
							case "EXPENSIVE":    $PRICE_LVL = 4; break;
							case "VERY_EXPENSIVE": $PRICE_LVL = 5; break;
						}
						$this->SetValue('act_level', $PRICE_LVL );
						break;
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
			if ($result == '') return json_encode($jsonform);
			$homes = json_decode($result, true);
			$value = [];
			$value[] = ["caption"=> "Select Home", "value"=> "0" ];
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

			// Element mit name "Home_ID" suchen und aktualisieren
			foreach ($jsonform['elements'] as $idx => &$el) {
				if (isset($el['name']) && $el['name'] === 'Home_ID') {
					$el['options'] = $value;
					$el['visible'] = true;
				}
			}

			// Colorsettings-Panel finden und Sichtbarkeit von HTML_FontColorHourDefault setzen
			foreach ($jsonform['elements'] as $idx => &$el) {
				if (isset($el['type']) && $el['type'] === 'ExpansionPanel' && isset($el['caption']) && $el['caption'] === 'Colorsettings') {
					if (isset($el['items']) && is_array($el['items'])) {
						foreach ($el['items'] as &$item) {
							if (isset($item['name']) && $item['name'] === 'HTML_FontColorHourDefault') {
								$item['visible'] = $this->ReadPropertyBoolean('HTML_FontColorHourDefaultSymcon');
							}
						}
					}
				}
			}

			// Barsettings-Panel finden und Sichtbarkeit des RowLayout "ShowPriceLevelEnhanced" setzen
			foreach ($jsonform['elements'] as $idx => &$el) {
				if (isset($el['type']) && $el['type'] === 'ExpansionPanel' && isset($el['caption']) && $el['caption'] === 'Barsettings') {
					if (isset($el['items']) && is_array($el['items'])) {
						foreach ($el['items'] as &$item) {
							if (isset($item['name']) && $item['name'] === 'ShowPriceLevelEnhanced') {
								$item['visible'] = $this->ReadPropertyBoolean('HTML_MarkPriceLevel');
							}
						}
					}
				}
			}

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
		
			// Detect resolution by step size (fallback to hourly)
			$todayArr = $prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["today"];
			$useQuarter = false;
			if (is_array($todayArr) && count($todayArr) >= 2) {
				$dt = strtotime($todayArr[1]['startsAt']) - strtotime($todayArr[0]['startsAt']);
				if ($dt > 0 && $dt <= 1200) { $useQuarter = true; }
			}
			if ($useQuarter) {
                // Aggregate 15-min segments into hourly entries for TODAY
                $groups = [];
                $rawQ = [];
                foreach ($todayArr as $row) {
                    $st = strtotime($row['startsAt']);
                    $keyH = date('YmdH', $st);
                    if (!isset($groups[$keyH])) {
                        $groups[$keyH] = [
                            'sumCt' => 0,
                            'cnt' => 0,
                            'start' => $st - ($st % 3600),
                            'level' => $row['level']
                        ];
                    }
                    $groups[$keyH]['sumCt'] += ($row['total'] * 100);
                    $groups[$keyH]['cnt'] += 1;
                    // write direct quarter-hourly variable for TODAY (T0)
					if ($this->ReadPropertyBoolean('Price_Variables_15m')) {
						$hQ = intval(date('G', $st));
						$mQ = intval(date('i', $st));
						$segQ = intdiv($mQ, 15);
						$idxQ = ($hQ * 4) + $segQ; // 0..95
						$this->SetPriceVariables('PT15M_T0_' . $idxQ, $row);
					}
                    // collect raw quarter-hourly (cent)
                    $rawQ[] = [
                        'start' => $st,
                        'end'   => $st + 900,
                        'Price' => $row['total'] * 100,
                        'Level' => $row['level']
                    ];
                }
                ksort($groups);
                $idx = 0;
                foreach ($groups as $g) {
                    $avgCt = ($g['cnt'] > 0) ? ($g['sumCt'] / $g['cnt']) : 0;
                    $var = 'PT60M_T0_' . $idx;
                    $this->SetPriceVariables($var, [ 'total' => ($avgCt / 100), 'level' => $g['level'], 'startsAt' => date('c', $g['start']) ]);
                    $result_array[] = [
                        'Ident' => $var,
                        'Price' => $avgCt,
                        'Level' => $g['level'],
                        'start' => $g['start'],
                        'end'   => $g['start'] + 3600
                    ];
                    $idx++;
                }
            } else {
                foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["today"] AS $key => $wa_price) {
                    $var = 'PT60M_T0_' . $key;
                    $this->SetPriceVariables($var, $wa_price);
                    $result_array[] = [ 'Ident' => $var,
                                        'Price' => $wa_price['total'] * 100,
                                        'Level' => $wa_price['level'],
                                        'start' => strtotime($wa_price['startsAt']),
                                        'end'   => strtotime("+1 hour", strtotime($wa_price['startsAt'])) ];
                }
            }
			if ($useQuarter) {
                // Aggregate 15-min segments into hourly entries for TOMORROW
                $tomArr = $prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["tomorrow"];
                if (is_array($tomArr) && count($tomArr) > 0) { $t1 = true; }
                $groups = [];
                foreach ($tomArr as $row) {
                    $st = strtotime($row['startsAt']);
                    $keyH = date('YmdH', $st);
                    if (!isset($groups[$keyH])) {
                        $groups[$keyH] = [
                            'sumCt' => 0,
                            'cnt' => 0,
                            'start' => $st - ($st % 3600),
                            'level' => $row['level']
                        ];
                    }
                    $groups[$keyH]['sumCt'] += ($row['total'] * 100);
                    $groups[$keyH]['cnt'] += 1;
                    // write direct quarter-hourly variable for TOMORROW (T1)
					if ($this->ReadPropertyBoolean('Price_Variables_15m')) {
						$hQ = intval(date('G', $st));
						$mQ = intval(date('i', $st));
						$segQ = intdiv($mQ, 15);
						$idxQ = ($hQ * 4) + $segQ; // 0..95
						$this->SetPriceVariables('PT15M_T1_' . $idxQ, $row);
					}
                    // collect raw quarter-hourly (cent)
                    $rawQ[] = [
                        'start' => $st,
                        'end'   => $st + 900,
                        'Price' => $row['total'] * 100,
                        'Level' => $row['level']
                    ];
                }
                ksort($groups);
                $idx = 0;
                foreach ($groups as $g) {
                    $t1 = true;
                    $avgCt = ($g['cnt'] > 0) ? ($g['sumCt'] / $g['cnt']) : 0;
                    $var = 'PT60M_T1_' . $idx;
                    $this->SetPriceVariables($var, [ 'total' => ($avgCt / 100), 'level' => $g['level'], 'startsAt' => date('c', $g['start']) ]);
                    $result_array[] = [
                        'Ident' => $var,
                        'Price' => $avgCt,
                        'Level' => $g['level'],
                        'start' => $g['start'],
                        'end'   => $g['start'] + 3600
                    ];
                    $idx++;
                }
                // store raw quarter-hourly list for visualization
                $this->WriteAttributeString('Price_Array_15m', json_encode($rawQ));
            } else {
                // clear raw quarter-hourly cache
                $this->WriteAttributeString('Price_Array_15m', '');
                foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["tomorrow"] AS $key => $wa_price) {
                    $t1 = true;
                    $var = 'PT60M_T1_' . $key;
                    $this->SetPriceVariables($var, $wa_price);
                    $result_array[] = [ 'Ident' => $var,
                                        'Price' => $wa_price['total'] * 100,
                                        'Level' => $wa_price['level'],
                                        'start' => strtotime($wa_price['startsAt']),
                                        'end'   => strtotime("+1 hour", strtotime($wa_price['startsAt'])) ];
                }
            }

			if (!$t1){
				for ($i = 0; $i <= 23; $i++) {
					$var = 'PT60M_T1_'.$i;
					$this->SetPriceVariablesZero($var);
					$result_array[] = [ 'Ident' => $var,
											'Price' => 0,
											'Level'	=> '' ];
				}
				$this->WriteAttributeBoolean('EEX_Received', false);
			}
			else{
				$this->WriteAttributeBoolean('EEX_Received', true);
			}
			
       		$this->WriteAttributeString("Price_Array", json_encode($result_array));
			// Statistik direkt aus dem frisch berechneten Array aktualisieren
			$this->Statistics($result_array);
	
			//update tile Visu
			$this->Update_Ahead_Price_Data();
			//$this->UpdateVisualizationValue($this->GetFullUpdateMessage());

			// Optional: write day-ahead prices to archive when enabled
			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->LogAheadPrices($result_array);
			}

		}

		private function SetLogging()
		{
			$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';  //ARchive Handler ermitteln
			$ar = IPS_GetInstanceListByModuleID($archive_handler);
			$ar_id = intval($ar[0] ?? 0);
			$this->WriteAttributeInteger("ar_handler", $ar_id);

			if ($ar_id > 0){
				$status = @AC_GetLoggingStatus($ar_id, @$this->GetIDForIdent("Ahead_Price"));
				if ($status == false){
					@AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("Ahead_Price"), true );
				}
				unset($status);
				
				$status = @AC_GetLoggingStatus($ar_id, @$this->GetIDForIdent("act_price"));
				if ($status == false){
					@AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("act_price"), true );
				}
				unset($status);
			}
			
			$this->CreateAheadChart();
		}

		private function CreateAheadChart()
		{
			if (!@$this->GetIDForIdent('TIBV2_Day_Ahead_Chart')){
				$var = $this->GetIDForIdent('Ahead_Price');
				if (!$var) { return; }
				$id = IPS_CreateMedia(4);
				IPS_SetParent($id,  $this->InstanceID);
				$payload = '{"datasets":[{"variableID":'.$var.',"fillColor":"#669c35","strokeColor":"#77bb41","timeOffset":-2,"visible":true,"title":"Preis Heute","type":"bar","side":"left"},{"variableID":'.$var.',"fillColor":"#f2f7b7","strokeColor":"#f2f7b7","timeOffset":-1,"visible":true,"title":"Preis Morgen","type":"bar","side":"left"}]}'
				;
				IPS_SetMediaFile($id,IPS_GetKernelDir().join(DIRECTORY_SEPARATOR, array("media", $id.".chart")),0);
				IPS_SetMediaContent($id, base64_encode($payload));
				IPS_SetName($id,'Day Ahead Chart');	
				IPS_SetIdent($id, 'TIBV2_Day_Ahead_Chart') ;
				IPS_SetPosition($id, 200);
			}
		}

		private function LogAheadPrices(array $result_array)
		{
			// ensure target variable and archive exist
			$varID = @$this->GetIDForIdent('Ahead_Price');
			if (!$varID) { return; }
			$ar_id = intval($this->ReadAttributeInteger('ar_handler'));
			if ($ar_id <= 0) {
				$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
				$ar = @IPS_GetInstanceListByModuleID($archive_handler);
				if (is_array($ar) && count($ar) > 0) {
					$ar_id = intval($ar[0]);
					$this->WriteAttributeInteger('ar_handler', $ar_id);
				} else { return; }
			}

			date_default_timezone_set('Europe/Berlin');
			$start = mktime(0, 0, 0, intval( date("m") ) , intval(date("d")-2), intval(date("Y")));
			$end = mktime(23, 59, 59, intval( date("m") ) , intval(date("d")-1), intval(date("Y")));

			@AC_DeleteVariableData($ar_id, $varID, $start, $end);

			foreach ($result_array as $res){
				if (!isset($res['Ident'], $res['Price'])) { continue; }
				$ident = $res['Ident'];
				$hour = intval(substr($ident, 9));
				$dayFlag = substr($ident, 7, 1);
				if ($dayFlag == '0'){
					@AC_AddLoggedValues($ar_id, $varID, [[ 'TimeStamp' => mktime($hour, 0, 1, intval(date('m')), intval(date('d')-2), intval(date('Y'))), 'Value' => $res['Price'] ]]);
				}
				elseif ($dayFlag == '1'){
					@AC_AddLoggedValues($ar_id, $varID, [[ 'TimeStamp' => mktime($hour, 0, 1, intval(date('m')), intval(date('d')-1), intval(date('Y'))), 'Value' => $res['Price'] ]]);
				}
			}
			$count = count($result_array);
			if ($count <= self::HTML_Max_HourAhead){
				@AC_AddLoggedValues($ar_id, $varID, [[ 'TimeStamp' => mktime(0, 0, 1, intval(date('m')), intval(date('d')-1), intval(date('Y'))), 'Value' => 0 ]]);
			}
			@AC_ReAggregateVariable($ar_id, $varID);
		}

        private function Update_Ahead_Price_Data()
        {
            $this->SendDebug(__FUNCTION__, $this->ReadAttributeString("Price_Array"), 0);

            // Demo-Modus: synthetische 15-Minuten-Daten erzeugen (24h × 4)
            if ($this->ReadPropertyBoolean('DemoMode')) {
                date_default_timezone_set('Europe/Berlin');
                $BarsPerHour = 4;
                $hoursToShow = min($this->ReadPropertyInteger("HTML_Default_HourAhead"), self::HTML_Max_HourAhead);
                $now = time();
                $now = $now - ($now % 3600); // auf Stundenanfang
                $base = 5.0; // ct/kWh, noch niedriger für deutlich negative Werte
                $swing = 20.0; // größere Amplitude für stärkere Negativphasen
                $noiseAmp = 3.0;
                $dataset = [];
                $avgPrices = [];
                for ($h=0; $h<$hoursToShow; $h++) {
                    $hourSum = 0; $hourCount = 0;
                    for ($q=0; $q<$BarsPerHour; $q++) {
                        $start = $now + ($h*3600) + ($q*900);
                        $end   = $start + 900;
                        $priceRaw = $base + sin(($h/24)*pi()*2)*$swing + ((mt_rand(0, 100)-50)/50.0)*$noiseAmp;
                        $price = round($priceRaw, 3); // negative erlaubt
                        $level = ($price < 18) ? 'VERY_CHEAP' : (($price < 20) ? 'CHEAP' : (($price < 22) ? 'NORMAL' : (($price < 24) ? 'EXPENSIVE' : 'VERY_EXPENSIVE')));
                        $dataset[] = [ 'start'=>$start, 'end'=>$end, 'price'=>$price, 'level'=>$level ];
                        $hourSum += $price; $hourCount++;
                    }
                    $avgPrices[] = $hourCount>0 ? round($hourSum/$hourCount, 3) : 0;
                }
                $this->WriteAttributeString('AVGPrice', json_encode($avgPrices));
                // Tile-Dataset folgt globalem 15m-Schalter (Enable_15m)
                $payloadTile = json_encode($dataset);
                if (!$this->ReadPropertyBoolean('Enable_15m')) {
                    // auf Stunden aggregieren
                    $agg = [];
                    for ($h=0; $h<$hoursToShow; $h++){
                        $sum=0; $cnt=0; $start = $now + ($h*3600);
                        for ($q=0; $q<4; $q++){ $sum += $dataset[$h*4+$q]['price']; $cnt++; }
                        $avg = $cnt>0 ? round($sum/$cnt, 3) : 0;
                        $level = $dataset[$h*4]['level'];
                        $agg[] = [ 'start'=>$start, 'end'=>$start+3600, 'price'=>$avg, 'level'=>$level ];
                    }
                    $payloadTile = json_encode($agg);
                }
                $this->WriteAttributeString('Ahead_Price_Data', $payloadTile);
                if ($this->ReadPropertyBoolean('Ahead_Price_Data_bool')){
                    // Immer Stundenpreise aggregieren für Variable 1
                    $payload60 = '';
                    if ($this->ReadPropertyBoolean('Enable_15m')){
                        $payload60 = $payloadTile;
                    } else {
                        $varArr = [];
                        for ($h=0; $h<$hoursToShow; $h++){
                            $sum=0; $cnt=0; $start = $now + ($h*3600);
                            for ($q=0; $q<4; $q++){ $sum += $dataset[$h*4+$q]['price']; $cnt++; }
                            $avg = $cnt>0 ? round($sum/$cnt, 3) : 0;
                            $level = $dataset[$h*4]['level'];
                            $varArr[] = [ 'start'=>$start, 'end'=>$start+3600, 'price'=>$avg, 'level'=>$level ];
                        }
                        $payload60 = json_encode($varArr);
                    }
                    if (@$this->GetIDForIdent('Ahead_Price_Data_60m')) { $this->SetValue('Ahead_Price_Data_60m', $payload60); }
                    // Variable 2: 15-Minuten-Preise nur bei global aktiv, sonst leer
                    $val15 = $this->ReadPropertyBoolean('Enable_15m') ? json_encode($dataset) : '';
                    if (@$this->GetIDForIdent('Ahead_Price_Data_15m')) { $this->SetValue('Ahead_Price_Data_15m', $val15); }
                }
                return;
            }

            // Normalbetrieb: Daten aus Price_Array übernehmen
            if ($this->ReadAttributeString("Price_Array") != '')
            {
                date_default_timezone_set('Europe/Berlin');
                $hoursToShow = min($this->ReadPropertyInteger("HTML_Default_HourAhead"), self::HTML_Max_HourAhead);
                if ($hoursToShow < 12) { $hoursToShow = 12; }
                $Ahead_Price_Data = [];
                $AVGPrice = [];
                $nowSec = time();

                $useRaw15 = false;
                $raw15 = [];
                if ($this->ReadPropertyBoolean('Enable_15m')){
                    $rawStr = $this->ReadAttributeString('Price_Array_15m');
                    $raw15 = $rawStr ? json_decode($rawStr, true) : [];
                    $useRaw15 = is_array($raw15) && count($raw15) > 0;
                }

                if ($useRaw15){
                    // Use true quarter-hourly items from API, aligned to full hour
                    usort($raw15, function($a,$b){ return ($a['start'] <=> $b['start']); });
                    $barsLimit = $hoursToShow * 4;
                    $barsAdded = 0;
                    $hourSums = [];
                    $hourCounts = [];
                    $startHour = $nowSec - ($nowSec % 3600);
                    foreach ($raw15 as $row){
                        $st = isset($row['start']) ? intval($row['start']) : 0;
                        $en = isset($row['end']) ? intval($row['end']) : ($st + 900);
                        if ($st < $startHour) { continue; }
                        if ($barsAdded >= $barsLimit) { break; }
                        $hk = date('YmdH', $st);
                        if (!isset($hourSums[$hk])) { $hourSums[$hk] = 0; $hourCounts[$hk] = 0; }
                        $p = isset($row['Price']) ? floatval($row['Price']) : 0.0;
                        $lvl = $row['Level'] ?? '';
                        $hourSums[$hk] += $p;
                        $hourCounts[$hk] += 1;
                        $Ahead_Price_Data[] = [
                            'start' => $st,
                            'end'   => $en,
                            'price' => round($p, 2),
                            'level' => $lvl
                        ];
                        $barsAdded++;
                    }
                    // AVG per hour from accumulated sums, in order of first appearance
                    $seen = [];
                    foreach ($Ahead_Price_Data as $row){
                        $hk = date('YmdH', $row['start']);
                        if (isset($seen[$hk])) { continue; }
                        $seen[$hk] = true;
                        if (isset($hourCounts[$hk]) && $hourCounts[$hk] > 0){
                            $AVGPrice[] = round($hourSums[$hk] / $hourCounts[$hk], 2);
                        }
                        if (count($AVGPrice) >= $hoursToShow) { break; }
                    }
                } else {
                    // Fallback: Stundenbalken exakt zeitbasiert ab aktueller Stunde ausrichten
                    $items = json_decode($this->ReadAttributeString('Price_Array'), true);
                    if (!is_array($items)) { return; }
                    // barsPerHour erkennen (1=Stunde, 4=15-min)
                    $barsPerHour = 1;
                    if (count($items) >= 2 && isset($items[0]['start'], $items[0]['end'])){
                        $dt = ($items[0]['end'] - $items[0]['start']);
                        if ($dt >= 899 && $dt <= 901) { $barsPerHour = 4; }
                    }
                    // Map für schnellen Zugriff per Startzeit
                    $byStart = [];
                    foreach ($items as $row){
                        if (!isset($row['start'])) continue;
                        $byStart[intval($row['start'])] = [
                            'price' => isset($row['Price']) ? floatval($row['Price']) : 0.0,
                            'level' => isset($row['Level']) ? strval($row['Level']) : ''
                        ];
                    }
                    // Ab aktueller Stunde exakt ein Stundenbalken pro Stunde generieren
                    $startHour = $nowSec - ($nowSec % 3600);
                    $AVGPrice = [];
                    for ($h=0; $h<$hoursToShow; $h++){
                        $st = $startHour + ($h * 3600);
                        $en = $st + 3600;
                        if (isset($byStart[$st])){
                            $p = round($byStart[$st]['price'], 2);
                            $lvl = $byStart[$st]['level'];
                            $Ahead_Price_Data[] = [ 'start'=>$st, 'end'=>$en, 'price'=>$p, 'level'=>$lvl ];
                            $AVGPrice[] = $p; // nur echte Werte in die Durchschnittsliste aufnehmen
                        } else {
                            // Kein Wert vorhanden -> Platzhalter 0 ct und leeres Level (nicht in Durchschnitt einbeziehen)
                            $Ahead_Price_Data[] = [ 'start'=>$st, 'end'=>$en, 'price'=>0.0, 'level'=>''];
                            // $AVGPrice: fehlende Stunden überspringen
                        }
                    }
                }

                $this->WriteAttributeString('AVGPrice', json_encode($AVGPrice));
                // Tile-Payload nach globalem 15m-Modus
                $payloadTile = json_encode($Ahead_Price_Data);
                $this->SendDebug(__FUNCTION__, $payloadTile, 0);
                $this->WriteAttributeString('Ahead_Price_Data', $payloadTile);
				if ($this->ReadPropertyBoolean('Ahead_Price_Data_bool')){
					// Bestimme Granularität des Tile-Datasets
					$tileBarsPerHour = 1;
					if (count($Ahead_Price_Data) >= 1 && isset($Ahead_Price_Data[0]['end'], $Ahead_Price_Data[0]['start'])){
						$dt0 = $Ahead_Price_Data[0]['end'] - $Ahead_Price_Data[0]['start'];
						if ($dt0 >= 899 && $dt0 <= 901) { $tileBarsPerHour = 4; }
					}
					// Variable 1: immer Stundenpreise
					$payload60 = '';
					if ($tileBarsPerHour === 1) {
						$payload60 = $payloadTile;
					} else {
						$varArr = [];
						$barsPerHourLocal = 4;
						$expectedBars = count($Ahead_Price_Data);
						$hoursLocal = intdiv($expectedBars + ($barsPerHourLocal-1), $barsPerHourLocal);
						for ($h=0; $h<$hoursLocal; $h++){
							$firstIdx = $h * $barsPerHourLocal;
							if (!isset($Ahead_Price_Data[$firstIdx])) break;
							$sum=0; $cnt=0; $start = $Ahead_Price_Data[$firstIdx]['start'];
							for ($q=0; $q<$barsPerHourLocal; $q++){
								$idx = $firstIdx + $q; if (!isset($Ahead_Price_Data[$idx])) break;
								$sum += ($Ahead_Price_Data[$idx]['price'] ?? 0); $cnt++;
							}
							$avg = $cnt>0 ? round($sum/$cnt, 2) : 0;
							$varArr[] = [ 'start'=>$start, 'end'=>$start+3600, 'price'=>$avg, 'level'=>($Ahead_Price_Data[$firstIdx]['level'] ?? '') ];
						}
						$payload60 = json_encode($varArr);
					}
					if (@$this->GetIDForIdent('Ahead_Price_Data_60m')) { $this->SetValue('Ahead_Price_Data_60m', $payload60); }
					// Variable 2: 15-Minuten-Preise nur wenn global aktiviert, sonst leer
					$payload15 = '';
					if ($this->ReadPropertyBoolean('Enable_15m')) {
						if ($tileBarsPerHour === 4) {
							$payload15 = $payloadTile;
						}
					}
					if (@$this->GetIDForIdent('Ahead_Price_Data_15m')) { $this->SetValue('Ahead_Price_Data_15m', $payload15); }
				}
			}
		}


		private function SetPriceVariables(string $var, array $wa_price)
		{	
			$priceCt = $wa_price['total'] * 100;
			// Direct quarter-hourly variable
			if (preg_match('/^PT15M_T([01])_(\d+)$/', $var)){
				if ($this->ReadPropertyBoolean('Price_Variables_15m')){
					$this->setvalue($var, $priceCt);
					IPS_Sleep(5);
				}
				return;
			}
			// Hourly variable
			if (preg_match('/^PT60M_T([01])_(\d+)$/', $var, $m)){
				if ($this->ReadPropertyBoolean('Price_Variables')){
					$this->setvalue($var, $priceCt);
					IPS_Sleep(5);
				}
				return;
			}
		}

		private function SetPriceVariablesZero(string $var)
		{	
			// Direct quarter-hourly variable
			if (preg_match('/^PT15M_T([01])_(\d+)$/', $var)){
				if ($this->ReadPropertyBoolean('Price_Variables_15m')){
					$this->setvalue($var, 0 );
					IPS_Sleep(5);
				}
				return;
			}
			// Hourly variable
			if (preg_match('/^PT60M_T([01])_(\d+)$/', $var, $m)){
				if ($this->ReadPropertyBoolean('Price_Variables')){
					$this->setvalue($var, 0 );
					IPS_Sleep(5);
				}
				return;
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
			$now = time();
			if ($this->ReadPropertyBoolean('Enable_15m')) {
				// schedule at next quarter-hour boundary (+5s buffer)
				$y = intval(date('Y', $now));
				$m = intval(date('m', $now));
				$d = intval(date('d', $now));
				$h = intval(date('G', $now));
				$min = intval(date('i', $now));
				$nextQuarter = (intdiv($min, 15) + 1) * 15;
				if ($nextQuarter >= 60) {
					$hNext = $h + 1;
					$dNext = $d + (($hNext >= 24) ? 1 : 0);
					$hNext = $hNext % 24;
					$time_new = mktime($hNext, 0, 5, $m, $dNext, $y);
				} else {
					$time_new = mktime($h, $nextQuarter, 5, $m, $d, $y);
				}
			} else {
				$h = intval(date('G', $now));
				$y = intval(date('Y', $now));
				$m = intval(date('m', $now));
				$d = intval(date('d', $now));
				if ($h < 23){
					$time_new = mktime($h+1, 0, 1, $m, $d, $y);
				} else {
					$time_new = mktime(0, 0, 10, $m, $d+1, $y);
				}
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
					$this->MaintainVariable("PT60M_T0_" . $i, $this->Translate('Today')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), 2, "Tibber.price.cent", 300 + $i, true);
                    IPS_Sleep(10);
				}
				for ($i = 0; $i <= 23; $i++) {
					$this->MaintainVariable("PT60M_T1_" . $i, $this->Translate('Tomorrow')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), 2, "Tibber.price.cent", 330 + $i, true);
                    IPS_Sleep(10);
				}
			} 
			else
			{
				for ($i = 0; $i <= 23; $i++) {

					if (@$this->GetIDForIdent("PT60M_T0_" . $i))
					{
						$this->MaintainVariable("PT60M_T0_" . $i, "", 2, "", 300 + $i, false);
						IPS_Sleep(10);
					}
				}
				for ($i = 0; $i <= 23; $i++) {
					if (@$this->GetIDForIdent("PT60M_T1_" . $i))
					{
						$this->MaintainVariable("PT60M_T1_" . $i, "", 2, "", 330 + $i, false);
						IPS_Sleep(10);
					}
				}
			}
			// 15-minute variables (optional)
			if ($this->ReadPropertyBoolean('Price_Variables_15m')){
				// Today (T0)
				for ($i = 0; $i <= 95; $i++) {
					$h = intdiv($i, 4);
					$m0 = ($i % 4) * 15;           // 0, 15, 30, 45
					$m1 = $m0 + 15;                // 15, 30, 45, 60
					$hEnd = ($h + intdiv($m1, 60)) % 24; // hour rollover
					$mEnd = $m1 % 60;               // minute rollover
					$label = sprintf('%s %02d:%02d %s %02d:%02d', $this->Translate('Today'), $h, $m0, $this->Translate('to'), $hEnd, $mEnd);
					$this->MaintainVariable("PT15M_T0_" . $i, $label, 2, "Tibber.price.cent", 400 + $i, true);
                    IPS_Sleep(10);
				}
				// Tomorrow (T1)
				for ($i = 0; $i <= 95; $i++) {
					$h = intdiv($i, 4);
					$m0 = ($i % 4) * 15;
					$m1 = $m0 + 15;
					$hEnd = ($h + intdiv($m1, 60)) % 24;
					$mEnd = $m1 % 60;
					$label = sprintf('%s %02d:%02d %s %02d:%02d', $this->Translate('Tomorrow'), $h, $m0, $this->Translate('to'), $hEnd, $mEnd);
					$this->MaintainVariable("PT15M_T1_" . $i, $label, 2, "Tibber.price.cent", 500 + $i, true);
                    IPS_Sleep(10);
				}
			}
			else
			{
				for ($i = 0; $i <= 95; $i++) {
					if (@$this->GetIDForIdent("PT15M_T0_" . $i)) { $this->MaintainVariable("PT15M_T0_" . $i, "", 2, "", 400 + $i, false); }
				}
				for ($i = 0; $i <= 95; $i++) {
					if (@$this->GetIDForIdent("PT15M_T1_" . $i)) { $this->MaintainVariable("PT15M_T1_" . $i, "", 2, "", 500 + $i, false); }
				}
			}
 
			//$this->RegisterVariableFloat("hourly_consumption", 'Stündlicher Verbrauch', "", 0);
			$this->RegisterVariableFloat("act_price", $this->Translate('actual price'), 'Tibber.price.cent', 0);
			$this->RegisterVariableInteger("act_level", $this->Translate('actual price level'), 'Tibber.price.level', 0);
			$this->RegisterVariableBoolean("RT_enabled", $this->Translate('realtime available'), '', 0);

			if ($this->ReadPropertyBoolean('Ahead_Price_Data_bool') == true){
				$this->RegisterVariableString("Ahead_Price_Data_60m", $this->Translate("Ahead price data variable for energy optimizer (hourly)"), "~TextBox", 0);
				$this->RegisterVariableString("Ahead_Price_Data_15m", $this->Translate("Ahead price data variable for energy optimizer (15-minute)"), "~TextBox", 0);
			}
			else
			{
				$this->MaintainVariable('Ahead_Price_Data_60m', "", 3, "~TextBox", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable('Ahead_Price_Data_15m', "", 3, "~TextBox", 0, false);
				IPS_Sleep(10);
			}

			// Day-ahead helper + logging to archive
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
				$this->MaintainVariable("minprice", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("maxprice", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("minmaxprice", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("lowtime", "", 1, "Tibber.price.hour", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("hightime", "", 1, "Tibber.price.hour", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("minprice_today", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("maxprice_today", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("minmaxprice_today", "", 2, "Tibber.price.cent", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("lowtime_today", "", 1, "Tibber.price.hour", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("hightime_today", "", 1, "Tibber.price.hour", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("no_level1", "", 1, "", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("no_level2", "", 1, "", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("no_level3", "", 1, "", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("no_level4", "", 1, "", 0, false);
				IPS_Sleep(10);
				$this->MaintainVariable("no_level5", "", 1, "", 0, false);
				IPS_Sleep(10);
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
				case "SetGradientOptimization":
					// Forward toggle state to the tile visualization without forcing a full reload
					$this->UpdateVisualizationValue(json_encode(['HTML_ColorGradientOptimization' => (bool)$Value]));
					$this->SendDebug(__FUNCTION__, 'Forward HTML_ColorGradientOptimization='.json_encode((bool)$Value), 0);
				break;
				case "reload":
					$this->Reload();
				break;
			}
		}

		private function Statistics(array $Data)
		{
			if (!$this->ReadPropertyBoolean('Statistics')) { return; }
			date_default_timezone_set('Europe/Berlin');
			// Partitioniere in HEUTE (T0) und MORGEN (T1) anhand des Idents
			$minT0 = INF; $minIdentT0 = '';
			$maxT0 = -INF; $maxIdentT0 = '';
			$minT1 = INF; $minIdentT1 = '';
			$maxT1 = -INF; $maxIdentT1 = '';
			$lvlCountT1 = ['VERY_CHEAP'=>0,'CHEAP'=>0,'NORMAL'=>0,'EXPENSIVE'=>0,'VERY_EXPENSIVE'=>0];
			$lvlCountAll = ['VERY_CHEAP'=>0,'CHEAP'=>0,'NORMAL'=>0,'EXPENSIVE'=>0,'VERY_EXPENSIVE'=>0];
			$hasT0 = false; $hasT1 = false;
			foreach ($Data as $row){
				if (!isset($row['Ident'])) { continue; }
				$ident = $row['Ident'];
				$price = isset($row['Price']) ? floatval($row['Price']) : 0.0;
				$level = $row['Level'] ?? '';
				// Stunden ohne Daten (leerer Level) aus allen Berechnungen ausschließen
				if ($level === '') { continue; }
				$dayFlag = substr($ident, 7, 1); // '0' or '1'
				if ($dayFlag === '0'){
					$hasT0 = true;
					if ($price < $minT0) { $minT0 = $price; $minIdentT0 = $ident; }
					if ($price > $maxT0) { $maxT0 = $price; $maxIdentT0 = $ident; }
				}
				elseif ($dayFlag === '1'){
					$hasT1 = true;
					if ($price < $minT1) { $minT1 = $price; $minIdentT1 = $ident; }
					if ($price > $maxT1) { $maxT1 = $price; $maxIdentT1 = $ident; }
					if (isset($lvlCountT1[$level])) { $lvlCountT1[$level]++; }
				}
				// Gesamtzähler unabhängig vom Tag
				if (isset($lvlCountAll[$level])) { $lvlCountAll[$level]++; }
			}
			// HEUTE setzen (falls vorhanden)
			if ($hasT0 && is_finite($minT0) && is_finite($maxT0)){
				$this->SetValue('minprice_today', $minT0);
				$this->SetValue('maxprice_today', $maxT0);
				$minHourT0 = intval(substr($minIdentT0, 9));
				$maxHourT0 = intval(substr($maxIdentT0, 9));
				$this->SetValue('lowtime_today', $minHourT0);
				$this->SetValue('hightime_today', $maxHourT0);
				$this->SetValue('minmaxprice_today', $maxT0 - $minT0);
			}
			// MORGEN setzen (falls vorhanden)
			if ($hasT1 && is_finite($minT1) && is_finite($maxT1)){
				$this->SetValue('minprice', $minT1);
				$this->SetValue('maxprice', $maxT1);
				$minHourT1 = intval(substr($minIdentT1, 9));
				$maxHourT1 = intval(substr($maxIdentT1, 9));
				$this->SetValue('lowtime', $minHourT1);
				$this->SetValue('hightime', $maxHourT1);
				$this->SetValue('minmaxprice', $maxT1 - $minT1);
			}
			// Setze die Zähler immer (Summe über heute+morgen), damit sie nicht 0 bleiben
			$this->SetValue('no_level1', $lvlCountAll['VERY_CHEAP']);
			$this->SetValue('no_level2', $lvlCountAll['CHEAP']);
			$this->SetValue('no_level3', $lvlCountAll['NORMAL']);
			$this->SetValue('no_level4', $lvlCountAll['EXPENSIVE']);
			$this->SetValue('no_level5', $lvlCountAll['VERY_EXPENSIVE']);
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
                $AVGPriceVal            = json_decode($this->ReadAttributeString("AVGPrice"),true);
                // Min/Max direkt aus dem aktuellen Tile-Dataset ermitteln (ohne Statistik-Variablen)
                // Platzhalter-Zeilen (level === '' oder nodata=true) ignorieren
                $tile = json_decode($this->ReadAttributeString('Ahead_Price_Data'), true);
                $vals = [];
                if (is_array($tile)) {
                    foreach ($tile as $row) {
                        $p = isset($row['price']) ? floatval($row['price']) : (isset($row['Price']) ? floatval($row['Price']) : null);
                        $lvl = isset($row['level']) ? $row['level'] : (isset($row['Level']) ? $row['Level'] : '');
                        $nd  = isset($row['nodata']) ? (bool)$row['nodata'] : false;
                        if (is_finite($p) && !$nd && (!is_string($lvl) || $lvl !== '')) {
                            $vals[] = $p;
                        }
                    }
                }
                if (!empty($vals)) {
                    $result['price_min'] = round(min($vals), 2);
                    $result['price_max'] = round(max($vals), 2);
                    $result['price_avg'] = round(array_sum($vals)/count($vals), 2);
                } else {
                    // Fallback: benutze AVGPrice (kann Platzhalter 0 enthalten)
                    $filtered = array_values(array_filter($AVGPriceVal, function($v){ return is_finite($v); }));
                    if (!empty($filtered)) {
                        $result['price_min'] = round(min($filtered), 2);
                        $result['price_max'] = round(max($filtered), 2);
                        $result['price_avg'] = round(array_sum($filtered)/count($filtered), 2);
                    }
                }
                $result['price_cur']    = $AVGPriceVal[0];
                // Provide exact current price from variable for frontend preference
                try {
                    $act = $this->GetValue('act_price');
                    if (is_numeric($act)) { $result['act_price'] = round(floatval($act), 2); }
                } catch (Exception $e) { /* ignore if not available */ }
			}
			
			            $result['BGCHour'] 			= sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorHour"));
            $result['BorderRadius']		= $this->ReadPropertyInteger("HTML_BorderRadius");
            // Pass HTML_Scale (percentage semantics): 0 disables; 1..10 = 10%-100% cropping of [0..min]
            $scaleVal = (int)$this->ReadPropertyInteger("HTML_Scale");
            if ($scaleVal < 0) { $scaleVal = 0; }
            if ($scaleVal > 10) { $scaleVal = 10; }
            $result['Scale']			= $scaleVal;
			$result['Gradient']			= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstartG")).", #".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstopG"));
			$result['GradientCurrent']	= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstartG_Current")).", #".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGCstopG_Current"));
			$result['MarkPriceLevel']	= $this->ReadPropertyBoolean("HTML_MarkPriceLevel");

			// Forward optimization switch to HTML
			$result['HTML_ColorGradientOptimization'] = $this->ReadPropertyBoolean("HTML_ColorGradientOptimization");

			// Font sizes for Bars / Hours / Prices as CSS clamp triplets
			$minB = $this->ReadPropertyInteger('HTML_FontSizeMinB');
			$defB = $this->ReadPropertyInteger('HTML_FontSizeDefB');
			$maxB = $this->ReadPropertyInteger('HTML_FontSizeMaxB');
			$result['FontSizeBars']   = $minB."px, ".$defB."vw, ".$maxB."px";

			$minH = $this->ReadPropertyInteger('HTML_FontSizeMinH');
			$defH = $this->ReadPropertyInteger('HTML_FontSizeDefH');
			$maxH = $this->ReadPropertyInteger('HTML_FontSizeMaxH');
			$result['FontSizeHours']  = $minH."px, ".$defH."vw, ".$maxH."px";

			$minP = $this->ReadPropertyInteger('HTML_FontSizeMinP');
			$defP = $this->ReadPropertyInteger('HTML_FontSizeDefP');
			$maxP = $this->ReadPropertyInteger('HTML_FontSizeMaxP');
			$result['FontSizePrices'] = $minP."px, ".$defP."vw, ".$maxP."px";

			$minS = $this->ReadPropertyInteger('HTML_FontSizeMinS');
			$defS = $this->ReadPropertyInteger('HTML_FontSizeDefS');
			$maxS = $this->ReadPropertyInteger('HTML_FontSizeMaxS');
			$result['FontSizeScale']  = $minS."px, ".$defS."vw, ".$maxS."px";
						
			$result['BGCPriceVC']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceVC"));
			$result['BGCPriceC']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceC"));
			$result['BGCPriceN']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceN"));
			$result['BGCPriceE']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceE"));
			$result['BGCPriceVE']					= "#".sprintf('%06X', $this->ReadPropertyInteger("HTML_BGColorPriceVE"));
			$result['PriceLevelThickness']			= $this->ReadPropertyInteger("HTML_PriceLevelThick");
			            // HourAhead: cap to module max; if at max AND OnlyPriceBars is enabled, further cap to available forecast hours
            $hourAhead = min($this->ReadPropertyInteger("HTML_Default_HourAhead"), self::HTML_Max_HourAhead);
            if ($hourAhead === self::HTML_Max_HourAhead && $this->ReadPropertyBoolean('HTML_OnlyPriceBars')) {
                $raw = json_decode($this->ReadAttributeString('Ahead_Price_Data'), true);
                $countOfAheadPriceData = is_array($raw) ? count($raw) : 0;
                $numberOfForcastHours = $this->ReadPropertyBoolean("Enable_15m")
                    ? intdiv($countOfAheadPriceData, 4)
                    : $countOfAheadPriceData;
                $hourAhead    = min($hourAhead, $numberOfForcastHours);
            }
			$result['HourAhead']                    = $hourAhead;

			$result['bar_price_round']				= $this->ReadPropertyInteger("HTML_Bar_Price_Round");
			$result['bar_price_vis_ct']				= $this->ReadPropertyBoolean("HTML_Bar_Price_vis_ct");
			$result['bar_show_prices']              = $this->ReadPropertyBoolean("HTML_Bar_Show_Prices");
			$result['show_grid']                    = $this->ReadPropertyBoolean("HTML_Show_Grid");
			$result['only_price_bars']              = $this->ReadPropertyBoolean("HTML_OnlyPriceBars");

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
				'HTML_FontSizeMinS'=> self::HTML_FontSizeMin,
				'HTML_FontSizeMaxS'=> self::HTML_FontSizeMax,
				'HTML_FontSizeDefS'=> self::HTML_FontSizeDef,
				'HTML_FontColorBars'=> self::HTML_Color_White,
				'HTML_FontColorHour'=> self::HTML_Color_White,
				'HTML_FontColorHourDefault'=> self::HTML_Color_Black,
				'HTML_BGColorHour'=> self::HTML_Color_Grey,
				'HTML_BorderRadius'=> self::HTML_Default_PX,
		'HTML_Scale'=> 0,
		'HTML_ColorGradientOptimization' => false,
		'HTML_BGCstartG'=> self::HTML_Color_Mint,
		'HTML_BGCstopG'=> self::HTML_Color_Darkmint,
		'HTML_BGCstartG_Current'=> self::HTML_Color_Orange,
		'HTML_BGCstopG_Current'=> self::HTML_Color_Red,
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
				'HTML_Bar_Show_Prices'=> true,
				'HTML_Show_Grid'=> true,
				'HTML_OnlyPriceBars'=> false,
				'HTML_Hour_WriteMode'=> self::HTML_Hour_WriteMode      

    ];
			
			foreach ($defaults as $data => $value)
			{
				$this->UpdateFormField($data, 'value', $value); 
				$this->SendDebug(__FUNCTION__,'set '.$data.' to default: '.$value,0);
			}
		}
	}