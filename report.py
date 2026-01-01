#!/usr/bin/env python3
"""
Binge-Worthy Channel Analytics Report

Analyzes click data from banalytiq.db and cross-references with channel/category
data from binge_worthy.db to identify top and bottom performers.

Uses time-normalized metrics (clicks per week) for fair comparison between
channels that have been tracked for different durations.

Usage: python report.py [--top N] [--bottom N] [--output FILE]
"""

import sqlite3
import argparse
from pathlib import Path
from datetime import datetime
from collections import defaultdict

# Paths relative to this script
SCRIPT_DIR = Path(__file__).parent
ANALYTICS_DB = SCRIPT_DIR / "banalytiq" / "banalytiq.db"
MAIN_DB = SCRIPT_DIR / "binge_worthy.db"

# Minimum weeks of data before considering a channel for removal
MIN_WEEKS_FOR_EVALUATION = 2


def get_click_data_with_time(analytics_conn):
    """Get click counts per channel with first/last click timestamps."""
    query = """
    SELECT
        CAST(REPLACE(url, '/channel?id=', '') AS INTEGER) as channel_id,
        COUNT(*) as clicks,
        MIN(dt) as first_click,
        MAX(dt) as last_click
    FROM analytics
    WHERE url LIKE '/channel?id=%'
        AND REPLACE(url, '/channel?id=', '') GLOB '[0-9]*'
        AND LENGTH(REPLACE(url, '/channel?id=', '')) <= 4
    GROUP BY channel_id
    """
    now = datetime.now().timestamp()
    result = {}
    for row in analytics_conn.execute(query):
        ch_id, clicks, first_click, last_click = row
        weeks = (now - first_click) / (7 * 24 * 3600)
        weeks = max(weeks, 0.1)  # Avoid division by zero
        result[ch_id] = {
            "clicks": clicks,
            "first_click": first_click,
            "last_click": last_click,
            "weeks": weeks,
            "clicks_per_week": clicks / weeks
        }
    return result


def get_channel_info(main_conn):
    """Get channel names and their categories."""
    channels = {}

    # Get channel names
    for row in main_conn.execute("SELECT id, name FROM channels"):
        channels[row[0]] = {"name": row[1], "categories": []}

    # Get categories for each channel
    cat_query = """
    SELECT cc.channel_id, cat.name
    FROM channel_categories cc
    JOIN categories cat ON cc.category_id = cat.id
    """
    for ch_id, cat_name in main_conn.execute(cat_query):
        if ch_id in channels:
            channels[ch_id]["categories"].append(cat_name)

    return channels


def get_category_stats(click_data, main_conn):
    """Calculate performance statistics per category using clicks per week.

    Only includes channels with MIN_WEEKS_FOR_EVALUATION+ weeks of data.
    """
    category_channels = defaultdict(list)

    query = """
    SELECT cat.name, cc.channel_id
    FROM categories cat
    JOIN channel_categories cc ON cat.id = cc.category_id
    """
    for cat_name, ch_id in main_conn.execute(query):
        category_channels[cat_name].append(ch_id)

    stats = []
    for cat, channels in category_channels.items():
        # Only include channels with enough tracking time
        valid_channels = [
            ch for ch in channels
            if ch in click_data and click_data[ch]["weeks"] >= MIN_WEEKS_FOR_EVALUATION
        ]
        cpw_list = [click_data[ch]["clicks_per_week"] for ch in valid_channels]
        clicks_list = [click_data[ch]["clicks"] for ch in valid_channels]

        if cpw_list:
            avg_cpw = sum(cpw_list) / len(cpw_list)
            total_clicks = sum(clicks_list)
            stats.append({
                "category": cat,
                "avg_clicks_per_week": avg_cpw,
                "total_clicks": total_clicks,
                "channel_count": len(cpw_list)
            })

    return sorted(stats, key=lambda x: x["avg_clicks_per_week"], reverse=True)


def generate_report(top_n=30, bottom_n=30):
    """Generate the full analytics report."""
    analytics_conn = sqlite3.connect(ANALYTICS_DB)
    main_conn = sqlite3.connect(MAIN_DB)

    click_data = get_click_data_with_time(analytics_conn)
    channel_info = get_channel_info(main_conn)
    category_stats = get_category_stats(click_data, main_conn)

    # Build channel performance list
    channel_performance = []
    new_channels = []  # Channels with no click data yet

    for ch_id, info in channel_info.items():
        data = click_data.get(ch_id)

        if data is None:
            # Channel has no clicks yet - it's new
            new_channels.append({
                "id": ch_id,
                "name": info["name"],
                "categories": ", ".join(info["categories"]) or "(uncategorized)",
            })
        else:
            first_seen = datetime.fromtimestamp(data["first_click"]).strftime('%Y-%m-%d')
            channel_performance.append({
                "id": ch_id,
                "name": info["name"],
                "categories": ", ".join(info["categories"]) or "(uncategorized)",
                "clicks": data["clicks"],
                "weeks": data["weeks"],
                "clicks_per_week": data["clicks_per_week"],
                "first_seen": first_seen
            })

    # Sort by clicks per week (time-normalized)
    channel_performance.sort(key=lambda x: x["clicks_per_week"])
    new_channels.sort(key=lambda x: x["name"])

    # Calculate summary stats (only for channels with data)
    total_clicks = sum(d["clicks"] for d in click_data.values())
    total_channels = len(channel_info)
    channels_with_data = len(click_data)

    # Average only from channels with enough tracking time
    valid_for_avg = [d for d in click_data.values() if d["weeks"] >= MIN_WEEKS_FOR_EVALUATION]
    avg_cpw = sum(d["clicks_per_week"] for d in valid_for_avg) / len(valid_for_avg) if valid_for_avg else 0

    # Generate report
    report = []
    report.append("=" * 90)
    report.append("BINGE-WORTHY CHANNEL ANALYTICS REPORT (Time-Normalized)")
    report.append(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    report.append("=" * 90)

    # Summary
    report.append("\n## SUMMARY")
    report.append(f"Total Channels: {total_channels}")
    report.append(f"Channels with click data: {channels_with_data}")
    report.append(f"New channels (no data yet): {len(new_channels)}")
    report.append(f"Total Channel Clicks: {total_clicks:,}")
    report.append(f"Average Clicks/Week (tracked {MIN_WEEKS_FOR_EVALUATION}+ weeks): {avg_cpw:.2f}")

    # New channels section (no data yet)
    if new_channels:
        report.append(f"\n## NEW CHANNELS (awaiting data - {len(new_channels)} channels)")
        report.append("-" * 90)
        report.append(f"{'Channel':<35} {'Categories'}")
        report.append("-" * 90)
        for ch in new_channels:
            report.append(f"{ch['name'][:34]:<35} {ch['categories'][:50]}")

    # Bottom performers (by clicks per week) - only channels with enough data
    established_channels = [ch for ch in channel_performance if ch["weeks"] >= MIN_WEEKS_FOR_EVALUATION]
    recent_channels = [ch for ch in channel_performance if ch["weeks"] < MIN_WEEKS_FOR_EVALUATION]

    report.append(f"\n## LOWEST PERFORMING CHANNELS (Bottom {min(bottom_n, len(established_channels))} by clicks/week, tracked {MIN_WEEKS_FOR_EVALUATION}+ weeks)")
    report.append("-" * 90)
    report.append(f"{'Cl/Wk':<7} {'Clicks':<7} {'Weeks':<6} {'First Seen':<12} {'Channel':<25} {'Categories'}")
    report.append("-" * 90)
    for ch in established_channels[:bottom_n]:
        report.append(
            f"{ch['clicks_per_week']:<7.2f} {ch['clicks']:<7} {ch['weeks']:<6.1f} "
            f"{ch['first_seen']:<12} {ch['name'][:24]:<25} {ch['categories'][:25]}"
        )

    # Recently added channels (less than MIN_WEEKS_FOR_EVALUATION weeks of data)
    if recent_channels:
        report.append(f"\n## RECENTLY ADDED CHANNELS (< {MIN_WEEKS_FOR_EVALUATION} weeks of data - {len(recent_channels)} channels)")
        report.append("-" * 90)
        report.append(f"{'Cl/Wk':<7} {'Clicks':<7} {'Weeks':<6} {'First Seen':<12} {'Channel':<25} {'Categories'}")
        report.append("-" * 90)
        for ch in sorted(recent_channels, key=lambda x: x["clicks_per_week"], reverse=True):
            report.append(
                f"{ch['clicks_per_week']:<7.2f} {ch['clicks']:<7} {ch['weeks']:<6.1f} "
                f"{ch['first_seen']:<12} {ch['name'][:24]:<25} {ch['categories'][:25]}"
            )

    # Top performers (by clicks per week)
    report.append(f"\n## TOP PERFORMING CHANNELS (Top {top_n} by clicks/week)")
    report.append("-" * 90)
    report.append(f"{'Cl/Wk':<7} {'Clicks':<7} {'Weeks':<6} {'First Seen':<12} {'Channel':<25} {'Categories'}")
    report.append("-" * 90)
    for ch in reversed(established_channels[-top_n:]):
        report.append(
            f"{ch['clicks_per_week']:<7.2f} {ch['clicks']:<7} {ch['weeks']:<6.1f} "
            f"{ch['first_seen']:<12} {ch['name'][:24]:<25} {ch['categories'][:25]}"
        )

    # Category performance - Top
    report.append(f"\n## TOP PERFORMING CATEGORIES (by avg clicks/week, channels tracked {MIN_WEEKS_FOR_EVALUATION}+ weeks)")
    report.append("-" * 70)
    report.append(f"{'Category':<25} {'Avg Cl/Wk':<12} {'Total Clicks':<14} {'Channels'}")
    report.append("-" * 70)
    for stat in category_stats[:15]:
        report.append(
            f"{stat['category']:<25} {stat['avg_clicks_per_week']:<12.2f} "
            f"{stat['total_clicks']:<14} {stat['channel_count']}"
        )

    # Category performance - Bottom
    report.append(f"\n## LOWEST PERFORMING CATEGORIES (by avg clicks/week)")
    report.append("-" * 70)
    report.append(f"{'Category':<25} {'Avg Cl/Wk':<12} {'Total Clicks':<14} {'Channels'}")
    report.append("-" * 70)
    for stat in category_stats[-15:]:
        report.append(
            f"{stat['category']:<25} {stat['avg_clicks_per_week']:<12.2f} "
            f"{stat['total_clicks']:<14} {stat['channel_count']}"
        )

    # Recommendations
    report.append("\n## RECOMMENDATIONS")
    report.append("-" * 70)

    # Categories to add more of
    report.append("\nCategories to ADD more channels (high clicks/week):")
    for stat in category_stats[:5]:
        if stat["avg_clicks_per_week"] > avg_cpw:
            report.append(f"  + {stat['category']} (avg: {stat['avg_clicks_per_week']:.2f} clicks/week)")

    # Categories to avoid
    report.append("\nCategories to AVOID adding (low clicks/week):")
    for stat in category_stats[-5:]:
        if stat["avg_clicks_per_week"] < avg_cpw:
            report.append(f"  - {stat['category']} (avg: {stat['avg_clicks_per_week']:.2f} clicks/week)")

    # Channels to consider removing (low clicks/week, tracked for at least MIN_WEEKS_FOR_EVALUATION weeks)
    underperformers = [ch for ch in established_channels if ch["clicks_per_week"] < 5]
    report.append(f"\nChannels to consider REMOVING (< 5 clicks/week, tracked {MIN_WEEKS_FOR_EVALUATION}+ weeks): {len(underperformers)}")
    for ch in underperformers:
        report.append(f"  x {ch['name']} ({ch['clicks_per_week']:.2f} cl/wk, {ch['weeks']:.1f} weeks)")

    # Orphaned channels (no categories)
    report.append("\nOrphaned channels (no categories assigned):")
    orphaned = [ch for ch in channel_performance if ch["categories"] == "(uncategorized)"]
    orphaned_new = [ch for ch in new_channels if ch["categories"] == "(uncategorized)"]
    all_orphaned = orphaned + [{"name": ch["name"], "clicks_per_week": 0} for ch in orphaned_new]

    if all_orphaned:
        for ch in all_orphaned:
            cpw = ch.get("clicks_per_week", 0)
            if cpw > 0:
                report.append(f"  ? {ch['name']} ({cpw:.2f} cl/wk)")
            else:
                report.append(f"  ? {ch['name']} (new)")
    else:
        report.append("  None")

    report.append("\n" + "=" * 90)

    analytics_conn.close()
    main_conn.close()

    return "\n".join(report)


def main():
    parser = argparse.ArgumentParser(description="Generate Binge-Worthy analytics report")
    parser.add_argument("--top", type=int, default=30, help="Number of top channels to show")
    parser.add_argument("--bottom", type=int, default=30, help="Number of bottom channels to show")
    parser.add_argument("--output", "-o", type=str, help="Output file (default: stdout)")
    args = parser.parse_args()

    report = generate_report(top_n=args.top, bottom_n=args.bottom)

    if args.output:
        Path(args.output).write_text(report)
        print(f"Report saved to {args.output}")
    else:
        print(report)


if __name__ == "__main__":
    main()
