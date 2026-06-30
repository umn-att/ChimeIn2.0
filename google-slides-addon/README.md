# Deploy Apps Script project for development

Requires [clasp](https://github.com/google/CLASP), `npm install -g clasp`

`clasp login` to authenticate to Google Drive

`clasp create --title [name] --type slides` - this will create a Google Slide doc of the given name along with an associated Apps Script project

`clasp push` deploys the apps script code and assets to the remote project