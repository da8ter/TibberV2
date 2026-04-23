<?php

trait TibberHelper
{

    private function GetHomesData()
    {
        // Build Request Data
        $request = '{ "query": "{viewer { homes { address { address1 } id appNickname} } }"}';
        $result = $this->CallTibber($request);
        if (!$result) return;		//Bei Fehler abbrechen

        $this->SendDebug(__FUNCTION__, $result, 0);
        $this->WriteAttributeString('Homes', $result);
        $this->GetConfigurationForm();
        $this->ReloadForm();
    }

    private function CallTibber(string $request)
		{
			// Wenn wir aktuell im Ratelimit-Banfenster sind: ohne echten API-Call abbrechen.
			$retryAfter = @$this->ReadAttributeInteger('ApiRetryAfter');
			if ($retryAfter && time() < $retryAfter) {
				$this->SendDebug('Call_tibber_blocked', 'ApiRetryAfter active until '.date('c', $retryAfter), 0);
				$this->SetStatus(205);
				return false;
			}

			$headers =  array('Authorization: Bearer '.$this->ReadPropertyString('Token'),  "Content-type: application/json");
			$this->SendDebug('HEADER', json_encode($headers), 0);
			$curl = curl_init();

			// Response-Header mitlesen für Retry-After
			$responseHeaders = [];
			curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, $this->ReadPropertyString('Api'));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  $request  );
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($curl, CURLOPT_TIMEOUT, 30);
			curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeaders) {
				$len = strlen($header);
				$p = strpos($header, ':');
				if ($p !== false) {
					$name = strtolower(trim(substr($header, 0, $p)));
					$val  = trim(substr($header, $p + 1));
					$responseHeaders[$name] = $val;
				}
				return $len;
			});

			$result = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$curlErrNo = curl_errno($curl);
			$curlErr = curl_error($curl);
			$this->SendDebug('Call_tibber_http', (string)$httpCode, 0);
			$this->SendDebug('Call_tibber_result', is_string($result) ? $result : '', 0);

			// Netzwerk-/Transportfehler behandeln
			if ($result === false || $curlErrNo !== 0) {
				$this->SendDebug('Call_tibber_curl_error', $curlErr, 0);
				curl_close($curl);
				$this->SetStatus(205);
				return false;
			}

			// HTTP 428 / 429 oder Rate-Limit-Meldung im Body -> Ban-Fenster setzen
			$isRateLimit = ($httpCode === 429 || $httpCode === 428) || (is_string($result) && strpos($result, 'Too many requests') !== false);
			if ($isRateLimit) {
				$wait = 300; // Default 5 Min
				if (isset($responseHeaders['retry-after'])) {
					$ra = $responseHeaders['retry-after'];
					if (is_numeric($ra)) {
						$wait = max(60, intval($ra));
					} else {
						$ts = strtotime($ra);
						if ($ts !== false) {
							$wait = max(60, $ts - time());
						}
					}
				}
				$this->WriteAttributeInteger('ApiRetryAfter', time() + $wait);
				$this->SendDebug('Call_tibber_ratelimit', 'HTTP '.$httpCode.' -> wait '.$wait.'s', 0);
				$this->SetStatus(205);
				curl_close($curl);
				return false;
			}

			// HTTP 401/403 -> Authentifizierung fehlgeschlagen
			if ($httpCode === 401 || $httpCode === 403) {
				$this->SendDebug('Call_tibber_auth_error', 'HTTP '.$httpCode, 0);
				$this->SetStatus(210);
				curl_close($curl);
				return false;
			}

			// HTTP 400 -> Bad Request (z.B. Schema-Änderung der API). Kein Retry.
			if ($httpCode === 400) {
				$this->SendDebug('Call_tibber_bad_request', 'HTTP '.$httpCode.' body: '.(is_string($result) ? $result : ''), 0);
				$this->SetStatus(206);
				curl_close($curl);
				return false;
			}

			// Server-Fehler 5xx -> kurzes Ban-Fenster, um Endlos-Retries zu vermeiden
			if ($httpCode >= 500 && $httpCode < 600) {
				$this->WriteAttributeInteger('ApiRetryAfter', time() + 600); // 10 Min
				$this->SendDebug('Call_tibber_server_error', 'HTTP '.$httpCode, 0);
				$this->SetStatus(205);
				curl_close($curl);
				return false;
			}

			curl_close($curl);

			$ar = json_decode($result, true);
			if (!is_array($ar)) {
				$this->SendDebug('Call_tibber_json_error', json_last_error_msg(), 0);
				return false;
			}

			// GraphQL-Fehler (oft mit HTTP 200). Modern: errors[].extensions.code
			if (array_key_exists('errors', $ar) && is_array($ar['errors']) && !empty($ar['errors'])){
				$firstErr = $ar['errors'][0];
				$errMsg   = isset($firstErr['message']) ? (string)$firstErr['message'] : '';
				$errCode  = isset($firstErr['extensions']['code']) ? (string)$firstErr['extensions']['code'] : '';
				$this->SendDebug('Call_tibber_gql_error', 'code='.$errCode.' msg='.$errMsg, 0);

				// Token-Probleme: sowohl alter Message-Text als auch neuer extensions.code
				if ($errCode === 'UNAUTHENTICATED' || $errMsg === 'Context creation failed: invalid token') {
					$this->SetStatus(210);
					return false;
				}
				// Serverseitige Fehler -> kurzes Ban-Fenster wie HTTP 5xx
				if ($errCode === 'INTERNAL_SERVER_ERROR') {
					$this->WriteAttributeInteger('ApiRetryAfter', time() + 600);
					$this->SetStatus(205);
					return false;
				}
				return false;
			}

			if (array_key_exists('data', $ar)){
				// Erfolgreicher Aufruf -> Ban-Fenster aufräumen und Status 205 -> 102 zurücksetzen
				if ($retryAfter) {
					$this->WriteAttributeInteger('ApiRetryAfter', 0);
				}
				if ($this->GetStatus() == 205) {
					$this->SetStatus(102);
				}
				return $result;
			}
			return false;
		}

		private function CheckRealtimeAvailable(bool $force = false)
		{
			// Cache: Realtime-Feature ändert sich selten -> max. 1x pro 24h abfragen
			$checkedAt = @$this->ReadAttributeInteger('RT_CheckedAt');
			if (!$force && $checkedAt && (time() - $checkedAt) < 86400) {
				$this->SendDebug('Realtime-Enabled', 'using cache', 0);
				return $this->ReadAttributeBoolean('RT_EnabledCached');
			}
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { features { realTimeConsumptionEnabled } }}}"}';
			$result = $this->CallTibber($request);
			$this->SendDebug('Realtime-Enabled', is_string($result) ? $result : '', 0);
			if (!$result) return $this->ReadAttributeBoolean('RT_EnabledCached');	//Fehler: letzten bekannten Wert nehmen
			$result_ar = json_decode($result, true);
			$enabled = (bool)($result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled'] ?? false);
			$this->WriteAttributeBoolean('RT_EnabledCached', $enabled);
			$this->WriteAttributeInteger('RT_CheckedAt', time());
			return $enabled;
			// RT 		$this->WriteAttributeBoolean('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);
			// query 	$this->SetValue('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);

		}

}