/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 * Portions Copyright (C) Philipp Kewisch, 2019 */

var abortControllers = new Map();
var tickets = new Map();

async function accountPrefs(accountId) {
  let {
    [`accounts.${accountId}.restURL`]: restURL,
    [`accounts.${accountId}.username`]: username,
    [`accounts.${accountId}.password`]: password
  } = await messenger.storage.local.get([
    `accounts.${accountId}.restURL`,
    `accounts.${accountId}.username`,
    `accounts.${accountId}.password`,
  ]);

  return { restURL, username, password };
}

async function updateAccount(account) {
  let resp = await request({
    account: account,
    method: "GET",
    url: "info",
  });

  let prefs = await accountPrefs(account.id);

  let { maxsize } = await resp.json();

  await messenger.cloudFile.updateAccount(account.id, {
    uploadSizeLimit: maxsize,
    configured: !!(prefs.restURL && prefs.username && prefs.password)
  });
}

async function request({ account, url, formData, signal, method="POST" }) {
  let prefs = await accountPrefs(account.id);
  let auth = "Basic " + btoa(prefs.username + ":" + prefs.password);

  if (!formData && method == "POST") {
    formData = new FormData();
  }

  if (formData && !formData.has("msg")) {
    formData.append("msg", "{}");
  }

  let headers = {
    "Authorization": auth,
    "X-Authorization": auth,
  };

  let resp = await fetch(new URL(url, prefs.restURL).href, {
    method: method,
    headers: headers,
    body: formData,
    signal: signal
  });

  if (!resp.ok) {
    let json = await resp.json();
    throw new Error(json.error);
  }

  return resp;
}


messenger.cloudFile.onFileUpload.addListener(
  async (account, { id, name, data }) => {
    let controller = new AbortController();
    abortControllers.set(id, controller);

    let formData = new FormData();
    console.log(data);
    formData.append("file", new Blob([data]), name);

    try {
      let resp = await request({
        account: account,
        url: "newticket",
        formData: formData,
        signal: controller.signal
      });
      let json = await resp.json();

      tickets.set(id, json.id);

      return { url: json.url };
    } finally {
      abortControllers.delete(id);
    }
  }
);

messenger.cloudFile.onFileUploadAbort.addListener((account, id) => {
  let controller = abortControllers.get(id);
  if (controller) {
    controller.abort();
    abortControllers.delete(id);
  }
});

messenger.cloudFile.onFileDeleted.addListener(async (account, id) => {
  let ticketId = tickets.get(id);
  if (ticketId) {
    await request({
      account: account,
      url: "purgeticket/" + ticketId
    });
    tickets.delete(id);
  }
});


messenger.cloudFile.getAllAccounts().then(async accounts => {
  await Promise.all(accounts.map(updateAccount));
});

messenger.cloudFile.onAccountAdded.addListener(account => updateAccount(account));

messenger.composeAction.onClicked.addListener(async (...args) => {
  // getLastFocused doesn't return compose windows for some reson
  let windows = await messenger.windows.getAll({ windowTypes: ["messageCompose"] });
  let focusedWindow = windows.find(window => window.focused);

  // TODO you might want to add a popup to select which account to create the grant for. Right now
  // Thunderbird just supports one account, so this is fine (tm).
  let accounts = await messenger.cloudFile.getAllAccounts();

  let identity = await messenger.dl.composeCurrentIdentity(focusedWindow.id);

  let formData = new FormData();
  formData.append("msg", JSON.stringify({ notify: identity.email }));

  let response = await request({
    account: accounts[0],
    url: "newgrant",
    formData: formData
  });

  let json = await response.json();

  let url = encodeURI(json.url);
  messenger.dl.composeInsertHTML(focusedWindow.id, `<a href="${url}">${url}</a>`);
});
