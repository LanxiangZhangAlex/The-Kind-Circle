/* Refresh only the public thread after a successful CF7 submission. */
document.addEventListener('wpcf7mailsent', async function(event) {
  if (!event.target.closest('.tkcq-submit')) return;
  const thread = document.querySelector('.tkcq-thread');
  if (!thread) return;
  try {
    const url = new URL(window.location.href);
    url.searchParams.delete('checkin_page');
    const response = await fetch(url.href, {credentials: 'same-origin', cache: 'no-store'});
    if (!response.ok) return;
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const updated = page.querySelector('.tkcq-thread');
    if (updated) thread.replaceWith(updated);
  } catch (_) { /* The saved check-in appears on the next normal page reload. */ }
});
