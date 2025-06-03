/*global dotclear */
'use strict';

dotclear.ready(() => {
  dotclear.dmLastSpams = dotclear.getData('dm_lastspams');

  const viewSpam = (line, _action = 'toggle', event = null) => {
    dotclear.dmViewComment(line, 'dmlse', !event.metaKey);
  };

  const getSpamCount = (icon) => {
    dotclear.services(
      'dmLastSpamsCount',
      (data) => {
        try {
          const response = JSON.parse(data);
          if (response?.success) {
            if (response?.payload.ret) {
              const nb_spams = response.payload.nb;
              if (nb_spams !== undefined && nb_spams !== dotclear.dmLastSpams.spamCount) {
                dotclear.badge(icon, {
                  id: 'dmls',
                  value: nb_spams,
                  remove: nb_spams <= 0,
                  sibling: true,
                  icon: true,
                });
                dotclear.dmLastSpams.spamCount = nb_spams;
              }
            }
          } else {
            console.log(dotclear.debug && response?.message ? response.message : 'Dotclear REST server error');
            return;
          }
        } catch (e) {
          console.log(e);
        }
      },
      (error) => {
        console.log(error);
      },
      true, // Use GET method
      { json: 1 },
    );
  };

  const getSpamsRows = (last_id) => {
    // Get new list
    dotclear.services(
      'dmLastSpamsRows',
      (data) => {
        try {
          const response = JSON.parse(data);
          if (response?.success) {
            if (response?.payload.ret) {
              const { counter } = response.payload;
              // Replace current list with the new one
              for (const item of document.querySelectorAll('#last-spams ul')) item.remove();
              for (const item of document.querySelectorAll('#last-spams p')) item.remove();
              if (counter > 0) {
                dotclear.dmLastSpams.lastCounter = Number(dotclear.dmLastSpams.lastCounter) + counter;
              }
              const title = document.querySelector('#last-spams h3');
              title?.insertAdjacentHTML('afterend', response.payload.list);

              if (dotclear.dmLastSpams.badge) {
                // Badge on module
                dotclear.badge(document.querySelector('#last-spams'), {
                  id: 'dmls',
                  value: dotclear.dmLastSpams.lastCounter,
                  remove: dotclear.dmLastSpams.lastcounter <= 0,
                });
              }
              // Bind every new lines for viewing comment content
              dotclear.expandContent({
                lines: document.querySelectorAll('#last-spams li.line'),
                callback: viewSpam,
              });
              for (const item of document.querySelectorAll('#last-spams ul')) item.classList.add('expandable');
            }
          } else {
            console.log(dotclear.debug && response?.message ? response.message : 'Dotclear REST server error');
            return;
          }
        } catch (e) {
          console.log(e);
        }
      },
      (error) => {
        console.log(error);
      },
      true, // Use GET method
      {
        json: 1,
        stored_id: dotclear.dmLastSpams.lastSpamId,
        last_id,
        last_counter: dotclear.dmLastSpams.lastCounter,
      },
    );
  };

  const checkLastSpams = () => {
    dotclear.services(
      'dmLastSpamsCheck',
      (data) => {
        try {
          const response = JSON.parse(data);
          if (response?.success) {
            if (response?.payload.ret && response.payload.nb > 0) {
              getSpamsRows(response.payload.last_id);
              // Store last comment id
              dotclear.dmLastSpams.lastSpamId = response.payload.last_id;
            }
          } else {
            console.log(dotclear.debug && response?.message ? response.message : 'Dotclear REST server error');
            return;
          }
        } catch (e) {
          console.log(e);
        }
      },
      (error) => {
        console.log(error);
      },
      true, // Use GET method
      {
        json: 1,
        last_id: dotclear.dmLastSpams.lastSpamId,
      },
    );
  };

  dotclear.expandContent({
    lines: document.querySelectorAll('#last-spams li.line'),
    callback: viewSpam,
  });
  for (const item of document.querySelectorAll('#last-spams ul')) item.classList.add('expandable');

  if (!dotclear.dmLastSpams.autoRefresh) {
    return;
  }

  // First pass
  checkLastSpams();
  dotclear.dmLastSpams.timer = setInterval(checkLastSpams, (dotclear.dmLastSpams.interval || 30) * 1000);

  if (!dotclear.dmLastSpams.badge) {
    return;
  }

  let icon_com = document.querySelector('#dashboard-main #icons p a[href="comments.php"]');
  if (!icon_com) {
    icon_com = document.querySelector('#dashboard-main #icons p #icon-process-comments-fav');
  }
  if (icon_com) {
    // First pass
    getSpamCount(icon_com);
    // Then fired every X seconds
    dotclear.dmLastSpams.timerSpam = setInterval(getSpamCount, (dotclear.dmLastSpams.interval || 30) * 1000, icon_com);
  }
});
