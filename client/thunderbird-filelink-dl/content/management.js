/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 * Portions Copyright (C) Philipp Kewisch, 2019 */

let currentAccountId = new URL(location.href).searchParams.get("accountId");

async function accountPrefs(accountId, values) {
  if (values) {
    return messenger.storage.local.set({
      [`accounts.${accountId}.restURL`]: values.restURL,
      [`accounts.${accountId}.username`]: values.username,
      [`accounts.${accountId}.password`]: values.password
    });
  } else {
    let {
      [`accounts.${accountId}.restURL`]: restURL,
      [`accounts.${accountId}.username`]: username,
      [`accounts.${accountId}.password`]: password
    } = await messenger.storage.local.get({
      [`accounts.${accountId}.restURL`]: "",
      [`accounts.${accountId}.username`]: "",
      [`accounts.${accountId}.password`]: ""
    });

    return { restURL, username, password };
  }
}

// https://davidwalsh.name/javascript-debounce-function
// which was adapted from underscore, which is under MIT license.
// Some adaptions to be more ES6-like and match eslint
// TODO This function needs to be adapted to wait for the promise that func
// returns to be resolved before calling the next func.
function debounce(func, wait, immediate) {
  let timeout;
  return function(...args) {
    let callNow = immediate && !timeout;
    clearTimeout(timeout);

    timeout = setTimeout(() => {
      timeout = null;
      if (!immediate) {
        func.apply(this, args);
      }
    }, wait);

    if (callNow) {
      func.apply(this, args);
    }
  };
}
// End MIT license code

document.body.addEventListener("input", debounce(async () => {
  let settings = {
    restURL: document.getElementById("restURL").value,
    username: document.getElementById("username").value,
    password: document.getElementById("password").value
  };

  await accountPrefs(currentAccountId, settings);

  let configured = !!(settings.restURL && settings.username && settings.password);
  await messenger.cloudFile.updateAccount(currentAccountId, { configured });
}, 500));

document.querySelectorAll("[data-l10n-id]").forEach(node => {
  node.textContent = messenger.i18n.getMessage(node.getAttribute("data-l10n-id"));
});

accountPrefs(currentAccountId).then((prefs) => {
  document.getElementById("restURL").value = prefs.restURL;
  document.getElementById("username").value = prefs.username;
  document.getElementById("password").value = prefs.password;
});
