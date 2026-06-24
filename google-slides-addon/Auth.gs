var USER_PROPS = PropertiesService.getUserProperties();

function getBaseUrl() {
  return USER_PROPS.getProperty('CHIMEIN_BASE_URL') || '';
}

function setBaseUrl(baseUrl) {
  if (!baseUrl) {
    throw new Error('Base URL is required.');
  }

  normalized = String(baseUrl)
    .trim()
    .replace(/^['"]+|['"]+$/g, '')  // strip wrapping quotes
    .replace(/\/+$/, '');           // strip trailing slashes

  if (!/^https?:\/\/.+/.test(normalized)) throw new Error('Base URL must start with http:// or https://');
  
  USER_PROPS.setProperty('CHIMEIN_BASE_URL', normalized);
  return normalized;
}

function getAuthToken() {
  return USER_PROPS.getProperty('CHIMEIN_API_TOKEN') || '';
}

function saveAuthToken(token) {
  if (!token) {
    throw new Error('Token is required.');
  }

  USER_PROPS.setProperty('CHIMEIN_API_TOKEN', String(token).trim());
  return { saved: true };
}

function clearAuthToken() {
  USER_PROPS.deleteProperty('CHIMEIN_API_TOKEN');
  return { cleared: true };
}

function getAuthStatus() {
  var token = getAuthToken();
  return {
    hasToken: !!token,
    baseUrl: getBaseUrl()
  };
}

function validateAuthToken() {
  var response = fetchChimes();
  return {
    valid: true,
    chimeCount: Array.isArray(response) ? response.length : 0
  };
}

function saveConnectionSettings(baseUrl, token) {
  var normalizedBaseUrl = setBaseUrl(baseUrl);
  saveAuthToken(token);
  var validation = validateAuthToken();

  return {
    saved: true,
    baseUrl: normalizedBaseUrl,
    validation: validation
  };
}

function clearConnectionSettings() {
  USER_PROPS.deleteProperty('CHIMEIN_BASE_URL');
  USER_PROPS.deleteProperty('CHIMEIN_API_TOKEN');
  return { cleared: true };
}

function clearAllUserSettings() {
  USER_PROPS.deleteAllProperties();
  return { cleared: true };
}
