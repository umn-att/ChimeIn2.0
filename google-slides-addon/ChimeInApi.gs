function apiRequest(path, options) {
  var token = getAuthToken();
  if (!token) {
    throw new Error('No API token saved.');
  }

  var config = options || {};
  var method = (config.method || 'get').toUpperCase();
  var baseUrl = getBaseUrl();
  var url = baseUrl + path;

  // Debug logging
  Logger.log('DEBUG apiRequest: baseUrl=' + baseUrl + ', path=' + path + ', url=' + url);

  var fetchOptions = {
    method: method,
    muteHttpExceptions: true,
    headers: {
      Authorization: 'Bearer ' + token,
      Accept: 'application/json',
      'User-Agent': 'Google-Apps-Script'
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
    var message = bodyText;
    try {
      var parsedError = JSON.parse(bodyText);
      if (parsedError && parsedError.message) {
        message = parsedError.message;
      }
    } catch (e) {
      // Keep raw body text if parse fails.
    }
    throw new Error('ChimeIn API request failed (' + status + '): ' + message);
  }

  if (!bodyText) {
    return null;
  }

  return JSON.parse(bodyText);
}

function fetchChimes() {
  return apiRequest('/api/chime', { method: 'get' });
}

function fetchOpenSessions(chimeId) {
  return apiRequest('/api/chime/' + encodeURIComponent(chimeId) + '/openQuestions', {
    method: 'get'
  });
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

function getSidebarChimeOptions() {
  var chimes = fetchChimes();
  if (!Array.isArray(chimes)) {
    return [];
  }

  return chimes
    .map(function (chime) {
      return {
        id: String(chime.id),
        name: chime.name || ('Chime ' + chime.id),
        accessCode: chime.access_code || ''
      };
    })
    .sort(function (a, b) {
      return a.name.localeCompare(b.name);
    });
}

function getSidebarSessionOptions(chimeId) {
  if (!chimeId) {
    return [];
  }

  var payload = fetchOpenSessions(chimeId);
  var sessions = payload && Array.isArray(payload.sessions) ? payload.sessions : [];

  return sessions.map(function (session) {
    var question = session.question || {};
    var folder = question.folder || {};
    return {
      id: String(session.id),
      questionText: question.text_preview || question.text || ('Question ' + (question.id || '')),
      folderName: folder.name || '',
      updatedAt: session.updated_at || ''
    };
  });
}
