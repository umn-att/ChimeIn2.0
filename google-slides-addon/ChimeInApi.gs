function apiRequest(path, options) {
  var token = getAuthToken();
  if (!token) {
    throw new Error('No API token saved.');
  }

  var config = options || {};
  var method = (config.method || 'get').toUpperCase();
  var url = getBaseUrl() + path;

  var fetchOptions = {
    method: method,
    muteHttpExceptions: true,
    headers: {
      Authorization: 'Bearer ' + token,
      Accept: 'application/json'
    }
  };

  if (config.payload !== undefined) {
    fetchOptions.contentType = 'application/json';
    fetchOptions.payload = JSON.stringify(config.payload);
  }

  var httpResponse = UrlFetchApp.fetch(url, fetchOptions);
  var status = httpResponse.getResponseCode();
  var bodyText = httpResponse.getContentText();

  if (status >= 400) {
    throw new Error('ChimeIn API request failed (' + status + '): ' + bodyText);
  }

  if (!bodyText) {
    return null;
  }

  return JSON.parse(bodyText);
}

function fetchChimes() {
  return apiRequest('/api/chime', { method: 'get' });
}

function fetchChime(chimeId) {
  return apiRequest('/api/chime/' + encodeURIComponent(chimeId), { method: 'get' });
}

function fetchFolder(chimeId, folderId) {
  return apiRequest('/api/chime/' + encodeURIComponent(chimeId) + '/folder/' + encodeURIComponent(folderId) + '/1', {
    method: 'get'
  });
}

function fetchSessionResults(chimeId, sessionId) {
  return apiRequest('/api/chime/' + encodeURIComponent(chimeId) + '/session/' + encodeURIComponent(sessionId) + '/results', {
    method: 'get'
  });
}

function fetchQrCodeBlob(chimeId, size, format) {
  var token = getAuthToken();
  if (!token) {
    throw new Error('No API token saved.');
  }

  var qrSize = size || 300;
  var qrFormat = format || 'png';
  var path = '/api/chime/' + encodeURIComponent(chimeId) + '/qrcode?size=' + encodeURIComponent(qrSize) + '&format=' + encodeURIComponent(qrFormat);

  var response = UrlFetchApp.fetch(getBaseUrl() + path, {
    method: 'get',
    muteHttpExceptions: true,
    headers: {
      Authorization: 'Bearer ' + token
    }
  });

  var status = response.getResponseCode();
  if (status >= 400) {
    throw new Error('QR fetch failed (' + status + ').');
  }

  return response.getBlob();
}
