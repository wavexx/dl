/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 * Portions Copyright (C) Philipp Kewisch, 2019 */

function ensureComposeWindow(window) {
  if (window.document.documentElement.getAttribute("windowtype") != "msgcompose") {
    throw new ExtensionError("Passed window is not a compose window");
  }
}

this.dl = class extends ExtensionAPI {
  getAPI(context) {
    let { tabManager } = context.extension;
    let windowTracker = Cu.getGlobalForObject(tabManager).windowTracker;

    return {
      dl: {
        async composeCurrentIdentity(windowId) {
          // Something like this will likely exist with bug 1590121 fixed.
          let composeWindow = windowTracker.getWindow(windowId, context);
          ensureComposeWindow(composeWindow);

          let identity = composeWindow.gCurrentIdentity;

          return {
            name: identity.fullName,
            email: identity.email,
            replyTo: identity.replyTo,
          };
        },

        async composeInsertHTML(windowId, html) {
          // This API won't survive well. When this stops working, check out bug 1590121.
          // Note it is also super unsafe, you need to make sure things are properly escaped in the
          // caller.

          let composeWindow = windowTracker.getWindow(windowId, context);
          ensureComposeWindow(composeWindow);

          let editor = composeWindow.GetCurrentEditor();
          editor.beginTransaction();
          editor.insertHTML(html);
          editor.endTransaction();
        }
      }
    };
  }
};
