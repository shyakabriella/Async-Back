import { useEffect, useMemo, useState } from "react";

const API_BASE = (
  import.meta.env.VITE_API_URL ||
  import.meta.env.VITE_API_BASE_URL ||
  import.meta.env.VITE_APP_API_URL ||
  "http://127.0.0.1:8000/api"
).replace(/\/+$/, "");

const EMPTY_DASHBOARD = {
  stats: {
    totalPrograms: 0,
    activePrograms: 0,
    totalApplications: 0,
    pendingApplications: 0,
    totalAgents: 0,
    activeAgents: 0,
    totalAttendance: 0,
    presentAttendance: 0,
  },
  performance: {
    successScore: 0,
    activeProgramPercent: 0,
    acceptedApplicationPercent: 0,
    paidReferralPercent: 0,
    slotFillPercent: 0,
  },
  categories: [],
  bestCategory: "N/A",
  lowestCategory: "N/A",
  recentRows: [],
  trainerSummary: {
    total_records: 0,
    present: 0,
    absent: 0,
    late: 0,
    excused: 0,
    not_marked: 0,
    total_salary: 0,
    total_paid: 0,
    total_unpaid: 0,
  },
  applicationBreakdown: {
    pending: 0,
    reviewed: 0,
    accepted: 0,
    rejected: 0,
    waitlisted: 0,
  },
  agentBreakdown: {
    totalStudents: 0,
    paidStudents: 0,
    notPaidStudents: 0,
    totalCommission: 0,
    expectedCommission: 0,
  },
};

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [dashboard, setDashboard] = useState(EMPTY_DASHBOARD);

  const stats = useMemo(
    () => [
      {
        title: "Total Programs",
        value: formatNumber(dashboard.stats.totalPrograms),
        badge: `${formatNumber(dashboard.stats.activePrograms)} Active`,
        note: "Programs from ProgramController",
        icon: <IconProgram />,
      },
      {
        title: "Applications",
        value: formatNumber(dashboard.stats.totalApplications),
        badge: `${formatNumber(dashboard.stats.pendingApplications)} Pending`,
        note: "Applications from ProgramApplicationController",
        icon: <IconApplication />,
      },
      {
        title: "Agents",
        value: formatNumber(dashboard.stats.totalAgents),
        badge: `${formatNumber(dashboard.stats.activeAgents)} Active`,
        note: "Agents from AgentController",
        icon: <IconUsers />,
      },
      {
        title: "Attendance Records",
        value: formatNumber(dashboard.stats.totalAttendance),
        badge: `${formatNumber(dashboard.stats.presentAttendance)} Present`,
        note: "Student + trainer attendance",
        icon: <IconAttendance />,
      },
    ],
    [dashboard]
  );

  useEffect(() => {
    loadDashboard();
  }, []);

  async function loadDashboard(isManualRefresh = false) {
    try {
      setError("");

      if (isManualRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      const [programsRes, applicationsRes, agentsRes, attendancesRes, trainerRes] =
        await Promise.all([
          apiGet("/programs"),
          apiGet("/applications?per_page=100"),
          apiGet("/agents"),
          apiGet("/attendances"),
          apiGet("/trainer-attendances"),
        ]);

      const programs = extractCollection(programsRes);
      const applications = extractCollection(applicationsRes);
      const agents = extractCollection(agentsRes);
      const attendances = extractCollection(attendancesRes);
      const trainerAttendances = extractCollection(trainerRes);
      const trainerSummary = trainerRes?.summary || {
        total_records: trainerAttendances.length,
        present: trainerAttendances.filter(
          (item) => normalizeStatus(item?.status) === "present"
        ).length,
        absent: trainerAttendances.filter(
          (item) => normalizeStatus(item?.status) === "absent"
        ).length,
        late: trainerAttendances.filter(
          (item) => normalizeStatus(item?.status) === "late"
        ).length,
        excused: trainerAttendances.filter(
          (item) => normalizeStatus(item?.status) === "excused"
        ).length,
        not_marked: trainerAttendances.filter(
          (item) => normalizeStatus(item?.status) === "not marked"
        ).length,
        total_salary: trainerAttendances.reduce(
          (sum, item) => sum + toNumber(item?.salary_amount),
          0
        ),
        total_paid: trainerAttendances
          .filter((item) => Boolean(item?.is_paid))
          .reduce((sum, item) => sum + toNumber(item?.salary_amount), 0),
        total_unpaid: trainerAttendances
          .filter((item) => !Boolean(item?.is_paid))
          .reduce((sum, item) => sum + toNumber(item?.salary_amount), 0),
      };

      const activePrograms = programs.filter(
        (item) => normalizeStatus(item?.status) === "active"
      ).length;

      const totalCapacity = programs.reduce(
        (sum, item) => sum + toNumber(item?.shift_summary?.total_capacity),
        0
      );
      const totalFilled = programs.reduce(
        (sum, item) => sum + toNumber(item?.shift_summary?.total_filled),
        0
      );
      const fullShifts = programs.reduce(
        (sum, item) => sum + toNumber(item?.shift_summary?.full_shifts),
        0
      );

      const pendingApplications = applications.filter(
        (item) => normalizeStatus(item?.status) === "pending"
      ).length;
      const reviewedApplications = applications.filter(
        (item) => normalizeStatus(item?.status) === "reviewed"
      ).length;
      const acceptedApplications = applications.filter(
        (item) => normalizeStatus(item?.status) === "accepted"
      ).length;
      const rejectedApplications = applications.filter(
        (item) => normalizeStatus(item?.status) === "rejected"
      ).length;
      const waitlistedApplications = applications.filter(
        (item) => normalizeStatus(item?.status) === "waitlisted"
      ).length;

      const activeAgents = agents.filter((item) => Boolean(item?.is_active)).length;

      const agentTotals = agents.reduce(
        (acc, agent) => {
          const stats = agent?.stats || {};
          acc.totalStudents += toNumber(stats?.total_students);
          acc.paidStudents += toNumber(stats?.paid_students);
          acc.notPaidStudents += toNumber(stats?.not_paid_students);
          acc.totalCommission += toNumber(stats?.total_commission);
          acc.expectedCommission += toNumber(stats?.expected_commission);
          return acc;
        },
        {
          totalStudents: 0,
          paidStudents: 0,
          notPaidStudents: 0,
          totalCommission: 0,
          expectedCommission: 0,
        }
      );

      const studentPresentCount = attendances.filter(
        (item) => normalizeStatus(item?.status) === "present"
      ).length;

      const totalAttendance =
        attendances.length + toNumber(trainerSummary?.total_records);
      const presentAttendance =
        studentPresentCount + toNumber(trainerSummary?.present);

      const activeProgramPercent = makePercent(activePrograms, programs.length);
      const acceptedApplicationPercent = makePercent(
        acceptedApplications,
        applications.length
      );
      const paidReferralPercent = makePercent(
        agentTotals.paidStudents,
        agentTotals.totalStudents
      );
      const slotFillPercent = makePercent(totalFilled, totalCapacity);

      const successScore = Math.round(
        averageOf([
          activeProgramPercent,
          acceptedApplicationPercent,
          paidReferralPercent,
          slotFillPercent,
        ])
      );

      const categoryCounts = [
        { label: "Programs", count: programs.length },
        { label: "Applications", count: applications.length },
        { label: "Agents", count: agents.length },
        { label: "Student Attendance", count: attendances.length },
        {
          label: "Trainer Attendance",
          count: toNumber(trainerSummary?.total_records),
        },
      ];

      const maxCategoryCount = Math.max(
        ...categoryCounts.map((item) => item.count),
        1
      );

      const categories = categoryCounts.map((item) => ({
        ...item,
        value: Math.round((item.count / maxCategoryCount) * 100),
      }));

      const sortedCategories = [...categoryCounts].sort((a, b) => b.count - a.count);
      const bestCategory = sortedCategories[0]?.label || "N/A";
      const lowestCategory =
        [...sortedCategories].sort((a, b) => a.count - b.count)[0]?.label || "N/A";

      const recentRows = buildRecentRows({
        programs,
        applications,
        agents,
        attendances,
        trainerAttendances,
      });

      setDashboard({
        stats: {
          totalPrograms: programs.length,
          activePrograms,
          totalApplications: applications.length,
          pendingApplications,
          totalAgents: agents.length,
          activeAgents,
          totalAttendance,
          presentAttendance,
        },
        performance: {
          successScore,
          activeProgramPercent,
          acceptedApplicationPercent,
          paidReferralPercent,
          slotFillPercent,
        },
        categories,
        bestCategory,
        lowestCategory,
        recentRows,
        trainerSummary,
        applicationBreakdown: {
          pending: pendingApplications,
          reviewed: reviewedApplications,
          accepted: acceptedApplications,
          rejected: rejectedApplications,
          waitlisted: waitlistedApplications,
        },
        agentBreakdown: agentTotals,
        extra: {
          fullShifts,
          totalCapacity,
          totalFilled,
        },
      });
    } catch (err) {
      console.error("Dashboard load failed:", err);
      setError(
        err?.message || "Failed to load dashboard data. Please check the API."
      );
      setDashboard(EMPTY_DASHBOARD);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  return (
    <div className="space-y-6">
      <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">
              AsyncAfrica Admin
            </p>
            <h1 className="mt-2 text-2xl font-extrabold text-slate-900 sm:text-3xl">
              Dashboard Overview
            </h1>
            <p className="mt-2 max-w-2xl text-sm leading-7 text-slate-500">
              Track programs, applications, agents, and attendance from one live
              dashboard.
            </p>
          </div>

          <button
            type="button"
            onClick={() => loadDashboard(true)}
            className="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
          >
            {refreshing ? "Refreshing..." : "Refresh Data"}
          </button>
        </div>

        {error ? (
          <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {error}
          </div>
        ) : null}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {stats.map((item) => (
          <StatCard
            key={item.title}
            title={item.title}
            value={loading ? "..." : item.value}
            badge={item.badge}
            note={item.note}
            icon={item.icon}
          />
        ))}
      </div>

      <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-extrabold text-slate-900 sm:text-xl">
              Performance & Activity Overview
            </h2>
            <p className="mt-1 text-sm text-slate-500">
              Real summary based on programs, applications, agents, and attendance.
            </p>
          </div>

          <div className="inline-flex items-center gap-2 rounded-2xl bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600">
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
            Live Summary
          </div>
        </div>

        <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-[1.1fr_0.9fr]">
          <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="text-lg font-extrabold text-slate-900">
                  Platform Performance
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                  Based on live controller data
                </p>
              </div>

              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-indigo-50 text-indigo-600">
                <IconChart />
              </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
              <div className="flex items-center justify-center">
                <div className="relative flex h-40 w-40 items-center justify-center rounded-full border-[12px] border-slate-200 bg-white">
                  <div className="absolute inset-0 rounded-full border-[12px] border-transparent border-t-indigo-600 border-r-sky-500 rotate-45" />
                  <div className="text-center">
                    <div className="text-3xl font-black text-slate-900">
                      {loading ? "..." : `${dashboard.performance.successScore}%`}
                    </div>
                    <div className="mt-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                      Health
                    </div>
                  </div>
                </div>
              </div>

              <div className="space-y-4">
                <ProgressItem
                  label="Active Programs"
                  value={dashboard.performance.activeProgramPercent}
                />
                <ProgressItem
                  label="Accepted Applications"
                  value={dashboard.performance.acceptedApplicationPercent}
                />
                <ProgressItem
                  label="Paid Referrals"
                  value={dashboard.performance.paidReferralPercent}
                />
                <ProgressItem
                  label="Program Slot Fill"
                  value={dashboard.performance.slotFillPercent}
                />
              </div>
            </div>

            <div className="mt-6 grid grid-cols-2 gap-3">
              <MiniInfoCard
                title="Total Trainer Salary"
                value={formatMoney(dashboard.trainerSummary.total_salary)}
              />
              <MiniInfoCard
                title="Paid Commission"
                value={formatMoney(dashboard.agentBreakdown.totalCommission)}
              />
            </div>
          </div>

          <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="text-lg font-extrabold text-slate-900">
                  Activity by Category
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                  Distribution across live dashboard resources
                </p>
              </div>

              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-sky-50 text-sky-600">
                <IconSpark />
              </div>
            </div>

            <div className="mt-6 space-y-5">
              {dashboard.categories.map((item) => (
                <Bar
                  key={item.label}
                  label={`${item.label} (${formatNumber(item.count)})`}
                  value={item.value}
                />
              ))}
            </div>

            <div className="mt-6 grid grid-cols-2 gap-3">
              <MiniInfoCard title="Best Category" value={dashboard.bestCategory} />
              <MiniInfoCard title="Lowest Activity" value={dashboard.lowestCategory} />
            </div>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
          <InfoPanel
            title="Application Status"
            rows={[
              ["Pending", dashboard.applicationBreakdown.pending],
              ["Reviewed", dashboard.applicationBreakdown.reviewed],
              ["Accepted", dashboard.applicationBreakdown.accepted],
              ["Rejected", dashboard.applicationBreakdown.rejected],
              ["Waitlisted", dashboard.applicationBreakdown.waitlisted],
            ]}
          />

          <InfoPanel
            title="Agent Referral Summary"
            rows={[
              ["Total Students", dashboard.agentBreakdown.totalStudents],
              ["Paid Students", dashboard.agentBreakdown.paidStudents],
              ["Not Paid Students", dashboard.agentBreakdown.notPaidStudents],
              [
                "Expected Commission",
                formatMoney(dashboard.agentBreakdown.expectedCommission),
              ],
              [
                "Paid Commission",
                formatMoney(dashboard.agentBreakdown.totalCommission),
              ],
            ]}
          />

          <InfoPanel
            title="Trainer Attendance Summary"
            rows={[
              ["Total Records", dashboard.trainerSummary.total_records],
              ["Present", dashboard.trainerSummary.present],
              ["Absent", dashboard.trainerSummary.absent],
              ["Late", dashboard.trainerSummary.late],
              ["Unpaid Salary", formatMoney(dashboard.trainerSummary.total_unpaid)],
            ]}
          />
        </div>

        <div className="mt-4 rounded-3xl border border-slate-200 bg-slate-50">
          <div className="border-b border-slate-200 px-5 py-5 sm:px-6">
            <h3 className="text-lg font-extrabold text-slate-900">
              Recent Activity
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Latest live records from programs, applications, agents, and attendance
            </p>
          </div>

          {dashboard.recentRows.length === 0 ? (
            <div className="px-5 py-10 text-center text-sm text-slate-500">
              {loading ? "Loading recent activity..." : "No recent activity found."}
            </div>
          ) : (
            <>
              <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-sm">
                  <thead className="bg-white text-slate-600">
                    <tr className="text-left">
                      <th className="px-6 py-4 font-bold">Date</th>
                      <th className="px-6 py-4 font-bold">Code</th>
                      <th className="px-6 py-4 font-bold">Title</th>
                      <th className="px-6 py-4 font-bold">Owner</th>
                      <th className="px-6 py-4 font-bold">Status</th>
                      <th className="px-6 py-4 font-bold">Source</th>
                    </tr>
                  </thead>

                  <tbody className="divide-y divide-slate-200">
                    {dashboard.recentRows.map((row) => (
                      <Row key={row.key} {...row} />
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="space-y-4 p-5 md:hidden">
                {dashboard.recentRows.map((row) => (
                  <MobileRow key={row.key} {...row} />
                ))}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ title, value, badge, note, icon }) {
  return (
    <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
            {title}
          </div>
          <div className="mt-3 text-3xl font-black text-slate-900">{value}</div>
          <div className="mt-2 flex items-center gap-2 text-xs">
            <span className="rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">
              {badge}
            </span>
            <span className="text-slate-500">{note}</span>
          </div>
        </div>

        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-indigo-50 text-indigo-600">
          {icon}
        </div>
      </div>
    </div>
  );
}

function MiniInfoCard({ title, value }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4">
      <div className="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
        {title}
      </div>
      <div className="mt-2 text-base font-extrabold text-slate-900">{value}</div>
    </div>
  );
}

function ProgressItem({ label, value }) {
  return (
    <div>
      <div className="flex items-center justify-between text-sm font-semibold text-slate-700">
        <span>{label}</span>
        <span>{value}%</span>
      </div>
      <div className="mt-2 h-3 overflow-hidden rounded-full bg-slate-200">
        <div
          className="h-full rounded-full bg-gradient-to-r from-indigo-600 to-sky-500"
          style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
        />
      </div>
    </div>
  );
}

function Bar({ label, value }) {
  return (
    <div>
      <div className="flex items-center justify-between text-sm font-semibold text-slate-700">
        <span>{label}</span>
        <span>{value}%</span>
      </div>
      <div className="mt-2 h-3 overflow-hidden rounded-full bg-slate-200">
        <div
          className="h-full rounded-full bg-gradient-to-r from-indigo-600 to-sky-500"
          style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
        />
      </div>
    </div>
  );
}

function InfoPanel({ title, rows }) {
  return (
    <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
      <h3 className="text-base font-extrabold text-slate-900">{title}</h3>

      <div className="mt-4 space-y-3">
        {rows.map(([label, value]) => (
          <div
            key={label}
            className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3"
          >
            <span className="text-sm font-medium text-slate-600">{label}</span>
            <span className="text-sm font-extrabold text-slate-900">{value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function Row({ date, code, title, owner, status, source }) {
  return (
    <tr>
      <td className="px-6 py-4 text-slate-600">{date}</td>
      <td className="px-6 py-4 font-bold text-slate-900">{code}</td>
      <td className="px-6 py-4">
        <div className="font-semibold text-slate-800">{title}</div>
      </td>
      <td className="px-6 py-4 text-slate-600">{owner}</td>
      <td className="px-6 py-4">
        <StatusBadge status={status} />
      </td>
      <td className="px-6 py-4 text-sm font-semibold text-indigo-600">{source}</td>
    </tr>
  );
}

function MobileRow({ date, code, title, owner, status, source }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-sm font-extrabold text-slate-900">{title}</div>
          <div className="mt-1 text-xs text-slate-500">
            {code} • {source}
          </div>
        </div>
        <StatusBadge status={status} />
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
        <div>
          <div className="text-[11px] uppercase tracking-[0.18em] text-slate-400">
            Date
          </div>
          <div className="mt-1 text-slate-700">{date}</div>
        </div>
        <div>
          <div className="text-[11px] uppercase tracking-[0.18em] text-slate-400">
            Owner
          </div>
          <div className="mt-1 text-slate-700">{owner}</div>
        </div>
      </div>
    </div>
  );
}

function StatusBadge({ status }) {
  const normalized = normalizeStatus(status);

  let styles = "border-slate-200 bg-slate-50 text-slate-700";

  if (
    ["active", "accepted", "paid", "present", "reviewed"].includes(normalized)
  ) {
    styles = "border-emerald-200 bg-emerald-50 text-emerald-700";
  } else if (
    ["pending", "not_paid", "waitlisted", "late", "draft", "not marked"].includes(
      normalized
    )
  ) {
    styles = "border-amber-200 bg-amber-50 text-amber-700";
  } else if (["rejected", "quit", "absent", "archived", "inactive"].includes(normalized)) {
    styles = "border-rose-200 bg-rose-50 text-rose-700";
  } else if (["excused"].includes(normalized)) {
    styles = "border-sky-200 bg-sky-50 text-sky-700";
  }

  return (
    <span
      className={`inline-flex rounded-full border px-3 py-1 text-xs font-bold ${styles}`}
    >
      {toStatusLabel(status)}
    </span>
  );
}

function buildRecentRows({
  programs,
  applications,
  agents,
  attendances,
  trainerAttendances,
}) {
  const rows = [];

  programs.forEach((item) => {
    rows.push({
      key: `program-${item.id}`,
      date: formatDate(item.created_at || item.updated_at || item.start_date),
      sortDate: item.created_at || item.updated_at || item.start_date,
      code: item.code || `PRG-${padCode(item.id)}`,
      title: item.name || "Untitled Program",
      owner:
        item.instructor ||
        (item.users_count ? `${item.users_count} linked users` : "Programs"),
      status: item.status || "Draft",
      source: "Program",
    });
  });

  applications.forEach((item) => {
    const fullName = [
      item?.applicant?.first_name,
      item?.applicant?.last_name,
    ]
      .filter(Boolean)
      .join(" ")
      .trim();

    rows.push({
      key: `application-${item.id}`,
      date: formatDate(item.submitted_at || item.created_at),
      sortDate: item.submitted_at || item.created_at,
      code: `APP-${padCode(item.id)}`,
      title: fullName || item?.program?.title || "Application",
      owner: item?.program?.title || item?.applicant?.email || "Application",
      status: item.status || "Pending",
      source: "Application",
    });
  });

  agents.forEach((item) => {
    rows.push({
      key: `agent-${item.id}`,
      date: formatDate(item.created_at || item.updated_at),
      sortDate: item.created_at || item.updated_at,
      code: `AGT-${padCode(item.id)}`,
      title: item.name || "Agent",
      owner: `${formatNumber(item?.stats?.total_students || 0)} students`,
      status: item.is_active ? "Active" : item.status || "Inactive",
      source: "Agent",
    });
  });

  attendances.forEach((item) => {
    const applicationName = [
      item?.application?.first_name,
      item?.application?.last_name,
    ]
      .filter(Boolean)
      .join(" ")
      .trim();

    rows.push({
      key: `attendance-${item.id}`,
      date: formatDate(item.attendance_date || item.created_at),
      sortDate: item.attendance_date || item.created_at,
      code: `ATT-${padCode(item.id)}`,
      title: applicationName || item?.program?.name || "Attendance Record",
      owner: item?.marked_by_user?.name || item?.markedByUser?.name || "Attendance",
      status: item.status || "Not Marked",
      source: "Student Attendance",
    });
  });

  trainerAttendances.forEach((item) => {
    rows.push({
      key: `trainer-attendance-${item.id}`,
      date: formatDate(item.attendance_date || item.created_at),
      sortDate: item.attendance_date || item.created_at,
      code: `TRN-${padCode(item.id)}`,
      title: item?.trainer?.name || "Trainer",
      owner: item?.trainer?.email || "Trainer Attendance",
      status: item?.is_paid ? "Paid" : item?.status || "Not Marked",
      source: "Trainer Attendance",
    });
  });

  return rows
    .sort((a, b) => getTimeValue(b.sortDate) - getTimeValue(a.sortDate))
    .slice(0, 8)
    .map((row) => ({
      ...row,
      date: row.date || "—",
    }));
}

async function apiGet(path) {
  const token = getAuthToken();

  const response = await fetch(makeUrl(path), {
    method: "GET",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data?.message || `Request failed for ${path}`);
  }

  return data;
}

function extractCollection(payload) {
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload)) return payload;
  return [];
}

function getAuthToken() {
  if (typeof window === "undefined") return "";

  return (
    localStorage.getItem("token") ||
    localStorage.getItem("auth_token") ||
    localStorage.getItem("access_token") ||
    ""
  );
}

function makeUrl(path) {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${API_BASE}${normalizedPath}`;
}

function normalizeStatus(value) {
  return String(value || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ");
}

function toStatusLabel(value) {
  const raw = String(value || "").trim();
  if (!raw) return "Unknown";

  if (raw === "not_paid") return "Not Paid";
  return raw;
}

function makePercent(value, total) {
  if (!total) return 0;
  return Math.round((toNumber(value) / toNumber(total)) * 100);
}

function averageOf(values) {
  const safe = values.filter((item) => Number.isFinite(item));
  if (!safe.length) return 0;
  return safe.reduce((sum, item) => sum + item, 0) / safe.length;
}

function toNumber(value) {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function formatNumber(value) {
  return new Intl.NumberFormat().format(toNumber(value));
}

function formatMoney(value, currency = "RWF") {
  return `${formatNumber(toNumber(value))} ${currency}`;
}

function formatDate(value) {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  });
}

function getTimeValue(value) {
  if (!value) return 0;
  const parsed = new Date(value).getTime();
  return Number.isNaN(parsed) ? 0 : parsed;
}

function padCode(value) {
  return String(value || 0).padStart(3, "0");
}

function IconProgram() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="M12 3 2 8l10 5 8-4v6h2V8L12 3Zm-6 8.5V16l6 3 6-3v-4.5l-6 3-6-3Z"
        fill="currentColor"
      />
    </svg>
  );
}

function IconApplication() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm7 1.5V9h4.5"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M9 13h6M9 17h6"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
    </svg>
  );
}

function IconUsers() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4ZM8 13a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm8 2c-3.314 0-6 1.79-6 4v1h12v-1c0-2.21-2.686-4-6-4ZM8 15c-2.67 0-5 1.34-5 3v2h5.5"
        fill="currentColor"
      />
    </svg>
  );
}

function IconAttendance() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="M7 2v3M17 2v3M4 7h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2.5 8 2 2 5-5"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function IconChart() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="M5 19V10M12 19V5M19 19v-7"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

function IconSpark() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path
        d="m12 3 1.8 4.7L18.5 9l-4.7 1.3L12 15l-1.8-4.7L5.5 9l4.7-1.3L12 3Zm6.5 12 1 2.5 2.5 1-2.5 1-1 2.5-1-2.5-2.5-1 2.5-1 1-2.5ZM5 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z"
        fill="currentColor"
      />
    </svg>
  );
}