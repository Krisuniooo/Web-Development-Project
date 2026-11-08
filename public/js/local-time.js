// Shows every <time data-local> in the viewer's own time zone and in the page's language.
// data-local="short" is for a list: only the hour today, the day and the hour this year, the full date before.
// data-local="full" is the whole date with seconds.
// The server writes the time in UTC, so without this script the page still shows it, only in UTC.
(() => {
  const language = document.documentElement.lang;
  const formats = {
    today: new Intl.DateTimeFormat(language, { timeStyle: 'short' }),
    thisYear: new Intl.DateTimeFormat(language, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }),
    earlier: new Intl.DateTimeFormat(language, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }),
    full: new Intl.DateTimeFormat(language, { dateStyle: 'long', timeStyle: 'medium' }),
  };
  const now = new Date();

  for (const time of document.querySelectorAll('time[data-local]')) {
    const date = new Date(time.dateTime);

    if (Number.isNaN(date.getTime())) {
      continue;
    }

    let format = formats.full;

    if (time.dataset.local === 'short') {
      if (date.toDateString() === now.toDateString()) {
        format = formats.today;
      } else if (date.getFullYear() === now.getFullYear()) {
        format = formats.thisYear;
      } else {
        format = formats.earlier;
      }
    }

    time.textContent = format.format(date);
  }
})();
