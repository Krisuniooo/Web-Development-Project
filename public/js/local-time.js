// Shows every <time data-local> in the viewer's own time zone and in the page's language.
// The server writes the time in UTC, so without this script the page still shows it, only in UTC.
(() => {
  const format = new Intl.DateTimeFormat(document.documentElement.lang, { dateStyle: 'medium', timeStyle: 'short' });

  for (const time of document.querySelectorAll('time[data-local]')) {
    const date = new Date(time.dateTime);

    if (!Number.isNaN(date.getTime())) {
      time.textContent = format.format(date);
    }
  }
})();
