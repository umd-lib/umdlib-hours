/**
 * @file
 * Computes an "Open now" / "Closed now" badge in the browser for the
 * location_page variant of the UmdLib Hours Today display.
 *
 * The badge is computed client-side (not in PHP) so it stays live per visitor
 * and is unaffected by Drupal's block cache. Library hours are evaluated in
 * America/New_York regardless of the visitor's local timezone.
 */
(function (Drupal, once) {
  "use strict";

  const TIMEZONE = "America/New_York";
  const MINUTES_PER_DAY = 1440;

  /**
   * Convert a 12-hour time string ("9:00AM", "12:00AM") to minutes since
   * midnight. Returns null if it can't be parsed.
   */
  function parseTime(value) {
    if (!value) {
      return null;
    }
    const match = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(value.trim());
    if (!match) {
      return null;
    }
    let hours = parseInt(match[1], 10) % 12;
    const minutes = parseInt(match[2], 10);
    if (/PM/i.test(match[3])) {
      hours += 12;
    }
    return hours * 60 + minutes;
  }

  /**
   * Current wall-clock time in TIMEZONE, as minutes since midnight.
   */
  function nowInEastern() {
    const parts = new Intl.DateTimeFormat("en-US", {
      timeZone: TIMEZONE,
      hour12: false,
      hour: "2-digit",
      minute: "2-digit",
    }).formatToParts(new Date());

    let hh = 0;
    let mm = 0;
    parts.forEach((part) => {
      if (part.type === "hour") {
        // Intl can emit '24' for midnight; normalize to 0.
        hh = parseInt(part.value, 10) % 24;
      } else if (part.type === "minute") {
        mm = parseInt(part.value, 10);
      }
    });
    return hh * 60 + mm;
  }

  /**
   * Is `now` (minutes) within a single from/to range? Handles 24-hour and
   * overnight ranges.
   */
  function inRange(from, to, now) {
    // 24 hours: 12:00AM - 12:00AM.
    if (from === 0 && to === 0) {
      return true;
    }
    let end = to;
    // Midnight end or overnight range: push end into the next day.
    if (to === 0 || to <= from) {
      end += MINUTES_PER_DAY;
    }
    if (now >= from && now < end) {
      return true;
    }
    // Also catch the early-morning tail of an overnight range.
    return now + MINUTES_PER_DAY >= from && now + MINUTES_PER_DAY < end;
  }

  /**
   * Determine open state from a single day's info object.
   * Returns true (open), false (closed), or null (unknown -> no badge).
   */
  function isOpen(info, now) {
    if (!info) {
      return null;
    }
    // Definitive whole-day states.
    if (info.status === "closed") {
      return false;
    }
    if (info.status === "24hours") {
      return true;
    }
    if (Array.isArray(info.hours) && info.hours.length) {
      for (const slot of info.hours) {
        const from = parseTime(slot.from);
        const to = parseTime(slot.to);
        if (from === null || to === null) {
          continue;
        }
        if (inRange(from, to, now)) {
          return true;
        }
      }
      return false;
    }
    // 'text' or anything unexpected with no usable hours: no definitive state.
    return null;
  }

  Drupal.behaviors.umdlibHoursOpenNow = {
    attach(context) {
      const elements = once(
        "umdlib-open-now",
        ".hours-status[data-hours]",
        context,
      );
      if (!elements.length) {
        return;
      }
      const now = nowInEastern();

      elements.forEach((el) => {
        let dates;
        try {
          dates = JSON.parse(el.getAttribute("data-hours"));
        } catch (e) {
          return;
        }
        if (!dates || typeof dates !== "object") {
          return;
        }
        // Today display returns a single date entry; use the first one.
        const keys = Object.keys(dates);
        if (!keys.length) {
          return;
        }
        const open = isOpen(dates[keys[0]], now);
        if (open === null) {
          return; // Leave the badge empty for unknown states.
        }
        el.textContent = open ? "Open now" : "Closed now";

        // Keep universal/base classes.
        el.classList.add("t-eyebrow", "s-stack-small");

        // Keep universal/base styles. Reveal the badge (the template hides it
        // with display:none until a definitive open/closed state is known).
        el.style.display = "inline-block";
        el.style.padding = "0.5rem";
        el.style.color = "var(--white)";
        el.style.backgroundColor = open ? "#037623" : "#e21833";
      });
    },
  };
})(Drupal, once);
