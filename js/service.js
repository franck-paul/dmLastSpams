/*global $, dotclear */
'use strict';

dotclear.dmLastSpamsCount = (icon) => {
  dotclear.services(
    'dmLastSpamsCount',
    (data) => {
      try {
        const response = JSON.parse(data);
        if (response?.success) {
          if (response?.payload.ret) {
            const nb_spams = response.payload.nb;
            if (nb_spams !== undefined && nb_spams != dotclear.dmLastSpams_SpamCount) {
              dotclear.badge(icon, {
                id: 'dmls',
                remove: nb_spams == 0,
                value: nb_spams,
                sibling: true,
                icon: true,
              });
              dotclear.dmLastSpams_SpamCount = nb_spams;
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

dotclear.dmLastSpamsRows = (last_id) => {
  dotclear.services(
    'dmLastSpamsRows',
    (data) => {
      try {
        const response = JSON.parse(data);
        if (response?.success) {
          if (response?.payload.ret) {
            const { counter } = response.payload;
            // Replace current list with the new one
            if ($('#last-spams ul').length) {
              $('#last-spams ul').remove();
            }
            if ($('#last-spams p').length) {
              $('#last-spams p').remove();
            }
            if (counter > 0) {
              dotclear.dmLastSpams_LastCounter = Number(dotclear.dmLastSpams_LastCounter) + counter;
            }
            $('#last-spams h3').after(response.payload.list);
            if (dotclear.dmLastSpams_Badge) {
              // Badge on module
              dotclear.badge($('#last-spams'), {
                id: 'dmls',
                value: dotclear.dmLastSpams_LastCounter,
                remove: dotclear.dmLastSpams_LastCounter == 0,
              });
            }
            // Bind every new lines for viewing comment content
            $.expandContent({
              lines: $('#last-spams li.line'),
              callback: dotclear.dmLastSpamsView,
            });
            $('#last-spams ul').addClass('expandable');
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
      stored_id: dotclear.dmLastSpams_LastSpamId,
      last_id,
      last_counter: dotclear.dmLastSpams_LastCounter,
    },
  );
};

dotclear.dmLastSpamsCheck = () => {
  dotclear.services(
    'dmLastSpamsCheck',
    (data) => {
      try {
        const response = JSON.parse(data);
        if (response?.success) {
          if (response?.payload.ret) {
            const new_spams = response.payload.nb;
            if (new_spams > 0) {
              // Get new list
              dotclear.dmLastSpamsRows(response.payload.last_id);

              // Store last comment id
              dotclear.dmLastSpams_LastSpamId = response.payload.last_id;
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
    {
      json: 1,
      last_id: dotclear.dmLastSpams_LastSpamId,
    },
  );
};

dotclear.dmLastSpamsView = (line, action = 'toggle', e = null) => {
  if ($(line).attr('id') == undefined) {
    return;
  }

  const spamId = $(line).attr('id').substring(4);
  const lineId = `dmlse${spamId}`;
  let li = document.getElementById(lineId);

  // If meta key down display content rather than HTML code
  const clean = !e.metaKey;

  if (li) {
    $(li).toggle();
    $(line).toggleClass('expand');
  } else {
    // Get comment content if possible
    dotclear.getCommentContent(
      spamId,
      (content) => {
        if (content) {
          li = document.createElement('li');
          li.id = lineId;
          li.className = 'expand';
          $(li).append(content);
          $(line).addClass('expand');
          line.parentNode.insertBefore(li, line.nextSibling);
          return;
        }
        // No content, content not found or server error
        $(line).removeClass('expand');
      },
      {
        metadata: false,
        clean,
      },
    );
  }
};

$(() => {
  Object.assign(dotclear, dotclear.getData('dm_lastspams'));

  $.expandContent({
    lines: $('#last-spams li.line'),
    callback: dotclear.dmLastSpamsView,
  });
  $('#last-spams ul').addClass('expandable');

  if (!dotclear.dmLastSpams_AutoRefresh) {
    return;
  }

  // First pass
  dotclear.dmLastSpamsCheck();
  // Auto refresh requested : Set interval between two checks for new comments and spam counter check
  dotclear.dmLastSpams_Timer = setInterval(dotclear.dmLastSpamsCheck, (dotclear.dmLastSpams_Interval || 30) * 1000);

  if (!dotclear.dmLastSpams_Badge) {
    return;
  }
  $('#last-spams').addClass('badgeable');
  let icon_com = $('#dashboard-main #icons p a[href="comments.php"]');
  if (!icon_com.length) {
    icon_com = $('#dashboard-main #icons p #icon-process-comments-fav');
  }
  if (icon_com.length) {
    // First pass
    dotclear.dmLastSpamsCount(icon_com);
    // Then fired every X seconds
    dotclear.dmLastSpams_TimerSpam = setInterval(
      dotclear.dmLastSpamsCount,
      (dotclear.dmLastSpams_Interval || 30) * 1000,
      icon_com,
    );
  }
});
