var USER_PROPS = PropertiesService.getUserProperties();

function getBaseUrl() {
  return USER_PROPS.getProperty('CHIMEIN_BASE_URL') || 'https://chimein.umn.edu';
}

function setBaseUrl(baseUrl) {
  if (!baseUrl) {
    throw new Error('Base URL is required.');
  }

  var normalized = String(baseUrl).trim().replace(/\/+$/, '');
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
  var response = apiRequest('/api/chime', { method: 'get' });
  return {
    valid: true,
    chimeCount: Array.isArray(response) ? response.length : 0
  };
}
