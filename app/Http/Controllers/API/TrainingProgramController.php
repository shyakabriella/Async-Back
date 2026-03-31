import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api";

function getAuthToken() {
  return (
    localStorage.getItem("token") ||
    sessionStorage.getItem("token") ||
    localStorage.getItem("auth_token") ||
    sessionStorage.getItem("auth_token") ||
    ""
  );
}

function formatDate(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  });
}

function normalizeText(value) {
  return String(value || "").trim();
}

function getProgramId(application) {
  return (
    application?.program?.id ||
    application?.program_id ||
    application?.program?.slug ||
    application?.program?.code ||
    "unassigned"
  );
}

function getProgramTitle(application) {
  return (
    normalizeText(application?.program?.title) ||
    normalizeText(application?.program?.name) ||
    normalizeText(application?.program_name) ||
    "Unassigned Program"
  );
}

function getProgramMeta(application) {
  return (
    normalizeText(application?.program?.slug) ||
    normalizeText(application?.program?.code) ||
    normalizeText(application?.program?.category) ||
    "No code"
  );
}

function getApplicantName(application) {
  const first = normalizeText(application?.applicant?.first_name);
  const last = normalizeText(application?.applicant?.last_name);
  return `${first} ${last}`.trim() || "-";
}

function matchesSearch(application, search) {
  const q = normalizeText(search).toLowerCase();
  if (!q) return true;

  const haystack = [
    getApplicantName(application),
    application?.applicant?.email,
    application?.applicant?.phone,
    getProgramTitle(application),
    getProgramMeta(application),
    application?.shift?.name,
    application?.experience_level,
    application?.status,
  ]
    .map((item) => normalizeText(item).toLowerCase())
    .join(" ");

  return haystack.includes(q);
}

function matchesStatus(application, statusFilter) {
  if (!statusFilter) return true;
  return normalizeText(application?.status).toLowerCase() ===
    normalizeText(statusFilter).toLowerCase();
}

function StatusBadge({ status }) {
  const current = String(status || "Pending").toLowerCase();

  const styles =
    current === "accepted"
      ? "bg-emerald-100 text-emerald-700 border-emerald-200"
      : current === "rejected"
      ? "bg-rose-100 text-rose-700 border-rose-200"
      : current === "reviewed"
      ? "bg-sky-100 text-sky-700 border-sky-200"
      : current === "waitlisted"
      ? "bg-amber-100 text-amber-700 border-amber-200"
      : "bg-violet-100 text-violet-700 border-violet-200";

  return (
    <span
      className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold ${styles}`}
    >
      {status || "Pending"}
    </span>
  );
}

function StatCard({ label, value, hint }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p className="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">
        {label}
      </p>
      <h3 className="mt-2 text-2xl font-black leading-none text-slate-900">
        {value}
      </h3>
      {hint ? (
        <p className="mt-2 text-xs text-slate-500">{hint}</p>
      ) : null}
    </div>
  );
}

function ProgramSummaryCard({ program, active, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full rounded-2xl border p-4 text-left shadow-sm transition ${
        active
          ? "border-[#6050F0] bg-[#f4f2ff] ring-2 ring-[#6050F0]/10"
          : "border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="truncate text-sm font-black text-slate-900">
            {program.title}
          </h3>
          <p className="mt-1 truncate text-xs text-slate-500">
            {program.meta}
          </p>
        </div>

        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">
          {program.total}
        </span>
      </div>

      <div className="mt-4 grid grid-cols-3 gap-2 text-center">
        <div className="rounded-xl bg-slate-50 px-2 py-2">
          <p className="text-[10px] uppercase tracking-[0.12em] text-slate-400">
            Pending
          </p>
          <p className="mt-1 text-sm font-black text-slate-900">
            {program.pending}
          </p>
        </div>

        <div className="rounded-xl bg-emerald-50 px-2 py-2">
          <p className="text-[10px] uppercase tracking-[0.12em] text-emerald-500">
            Accepted
          </p>
          <p className="mt-1 text-sm font-black text-emerald-700">
            {program.accepted}
          </p>
        </div>

        <div className="rounded-xl bg-rose-50 px-2 py-2">
          <p className="text-[10px] uppercase tracking-[0.12em] text-rose-500">
            Rejected
          </p>
          <p className="mt-1 text-sm font-black text-rose-700">
            {program.rejected}
          </p>
        </div>
      </div>
    </button>
  );
}

function MobileApplicationCard({
  application,
  onView,
  onDelete,
  deletingId,
}) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="truncate text-sm font-bold text-slate-900">
            {getApplicantName(application)}
          </h3>
          <p className="mt-1 truncate text-xs text-slate-500">
            {application?.applicant?.email || "-"}
          </p>
        </div>

        <StatusBadge status={application?.status} />
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 text-xs">
        <div>
          <p className="text-slate-400">Program</p>
          <p className="mt-1 font-semibold text-slate-800">
            {getProgramTitle(application)}
          </p>
        </div>

        <div>
          <p className="text-slate-400">Shift</p>
          <p className="mt-1 font-semibold text-slate-800">
            {application?.shift?.name || "-"}
          </p>
        </div>

        <div>
          <p className="text-slate-400">Phone</p>
          <p className="mt-1 font-semibold text-slate-800">
            {application?.applicant?.phone || "-"}
          </p>
        </div>

        <div>
          <p className="text-slate-400">Submitted</p>
          <p className="mt-1 font-semibold text-slate-800">
            {formatDate(application?.submitted_at)}
          </p>
        </div>
      </div>

      <div className="mt-4 flex gap-2">
        <button
          type="button"
          onClick={onView}
          className="flex-1 rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100"
        >
          View
        </button>

        <button
          type="button"
          onClick={onDelete}
          disabled={deletingId === application.id}
          className="flex-1 rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {deletingId === application.id ? "Deleting..." : "Delete"}
        </button>
      </div>
    </div>
  );
}

export default function ApplicationReceived() {
  const navigate = useNavigate();

  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pageLoading, setPageLoading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [error, setError] = useState("");
  const [successMessage, setSuccessMessage] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [activeProgramId, setActiveProgramId] = useState("");

  const fetchApplications = useCallback(
    async ({ silent = false } = {}) => {
      try {
        if (silent) {
          setPageLoading(true);
        } else {
          setLoading(true);
        }

        setError("");
        setSuccessMessage("");

        const token = getAuthToken();

        const params = new URLSearchParams();
        if (search.trim()) params.append("search", search.trim());
        if (statusFilter) params.append("status", statusFilter);

        const url = `${API_BASE_URL.replace(/\/+$/, "")}/applications${
          params.toString() ? `?${params.toString()}` : ""
        }`;

        const response = await fetch(url, {
          headers: {
            Accept: "application/json",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(result?.message || "Failed to load applications.");
        }

        const rows = Array.isArray(result?.data?.data)
          ? result.data.data
          : Array.isArray(result?.data)
          ? result.data
          : [];

        setApplications(rows);
      } catch (err) {
        setError(err?.message || "Could not load applications.");
      } finally {
        setLoading(false);
        setPageLoading(false);
      }
    },
    [search, statusFilter]
  );

  useEffect(() => {
    fetchApplications();
  }, [fetchApplications]);

  const groupedPrograms = useMemo(() => {
    const map = new Map();

    applications.forEach((application) => {
      const id = String(getProgramId(application));
      const title = getProgramTitle(application);
      const meta = getProgramMeta(application);

      if (!map.has(id)) {
        map.set(id, {
          id,
          title,
          meta,
          applications: [],
          total: 0,
          pending: 0,
          accepted: 0,
          rejected: 0,
        });
      }

      const group = map.get(id);
      group.applications.push(application);
      group.total += 1;

      const status = normalizeText(application?.status).toLowerCase();
      if (status === "pending") group.pending += 1;
      if (status === "accepted") group.accepted += 1;
      if (status === "rejected") group.rejected += 1;
    });

    return Array.from(map.values()).sort((a, b) => {
      if (b.total !== a.total) return b.total - a.total;
      return a.title.localeCompare(b.title);
    });
  }, [applications]);

  useEffect(() => {
    if (!groupedPrograms.length) {
      setActiveProgramId("");
      return;
    }

    const stillExists = groupedPrograms.some(
      (program) => String(program.id) === String(activeProgramId)
    );

    if (!stillExists) {
      setActiveProgramId(String(groupedPrograms[0].id));
    }
  }, [groupedPrograms, activeProgramId]);

  const selectedProgram = useMemo(() => {
    return (
      groupedPrograms.find(
        (program) => String(program.id) === String(activeProgramId)
      ) || null
    );
  }, [groupedPrograms, activeProgramId]);

  const visibleApplications = useMemo(() => {
    const rows = selectedProgram?.applications || [];

    return rows.filter(
      (application) =>
        matchesSearch(application, searchInput) &&
        matchesStatus(application, statusFilter)
    );
  }, [selectedProgram, searchInput, statusFilter]);

  const overallStats = useMemo(() => {
    const total = applications.length;
    const pending = applications.filter(
      (item) => normalizeText(item.status).toLowerCase() === "pending"
    ).length;
    const accepted = applications.filter(
      (item) => normalizeText(item.status).toLowerCase() === "accepted"
    ).length;
    const rejected = applications.filter(
      (item) => normalizeText(item.status).toLowerCase() === "rejected"
    ).length;

    return {
      total,
      pending,
      accepted,
      rejected,
      programs: groupedPrograms.length,
    };
  }, [applications, groupedPrograms]);

  async function handleDelete(applicationId) {
    const confirmed = window.confirm(
      "Are you sure you want to delete this application?"
    );

    if (!confirmed) return;

    try {
      setDeletingId(applicationId);
      setError("");
      setSuccessMessage("");

      const token = getAuthToken();

      const response = await fetch(
        `${API_BASE_URL.replace(/\/+$/, "")}/applications/${applicationId}`,
        {
          method: "DELETE",
          headers: {
            Accept: "application/json",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        }
      );

      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(result?.message || "Failed to delete application.");
      }

      setApplications((prev) =>
        prev.filter((item) => String(item.id) !== String(applicationId))
      );
      setSuccessMessage("Application deleted successfully.");
    } catch (err) {
      setError(err?.message || "Could not delete application.");
    } finally {
      setDeletingId(null);
    }
  }

  function handleApplyFilters() {
    setSearch(searchInput);
  }

  function handleResetFilters() {
    setSearchInput("");
    setSearch("");
    setStatusFilter("");
  }

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
        <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.22em] text-[#6050F0]">
              Training Management
            </p>
            <h1 className="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">
              Applications by Program
            </h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
              Programs are separated now. Click any program to see only the
              applicants enrolled in that program.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => fetchApplications({ silent: true })}
              className="rounded-full border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 sm:text-sm"
            >
              {pageLoading ? "Refreshing..." : "Refresh"}
            </button>
          </div>
        </div>

        <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <StatCard
            label="Total Applications"
            value={overallStats.total}
            hint="All submitted applications"
          />
          <StatCard
            label="Programs"
            value={overallStats.programs}
            hint="Programs with applicants"
          />
          <StatCard
            label="Pending"
            value={overallStats.pending}
            hint="Awaiting review"
          />
          <StatCard
            label="Accepted"
            value={overallStats.accepted}
            hint="Approved applicants"
          />
          <StatCard
            label="Selected Program"
            value={selectedProgram?.total || 0}
            hint={selectedProgram?.title || "No program selected"}
          />
        </div>

        <div className="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="grid gap-3 md:grid-cols-3">
            <div className="md:col-span-2">
              <label className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">
                Search applicant in selected program
              </label>
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Search by name, email, phone..."
                className="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#7A6CF5]"
              />
            </div>

            <div>
              <label className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">
                Status
              </label>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-[#7A6CF5]"
              >
                <option value="">All status</option>
                <option value="Pending">Pending</option>
                <option value="Reviewed">Reviewed</option>
                <option value="Accepted">Accepted</option>
                <option value="Rejected">Rejected</option>
                <option value="Waitlisted">Waitlisted</option>
              </select>
            </div>
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={handleApplyFilters}
              className="rounded-full bg-[#6050F0] px-5 py-2.5 text-xs font-bold text-white transition hover:bg-[#7A6CF5] sm:text-sm"
            >
              Filter
            </button>

            <button
              type="button"
              onClick={handleResetFilters}
              className="rounded-full border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 sm:text-sm"
            >
              Reset
            </button>
          </div>
        </div>

        {successMessage ? (
          <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {successMessage}
          </div>
        ) : null}

        {error ? (
          <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
            Loading applications...
          </div>
        ) : groupedPrograms.length === 0 ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
            No applications found.
          </div>
        ) : (
          <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h2 className="text-base font-black text-slate-900">
                    Programs
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    Click a program to see its applicants
                  </p>
                </div>

                <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">
                  {groupedPrograms.length}
                </span>
              </div>

              <div className="space-y-3">
                {groupedPrograms.map((program) => (
                  <ProgramSummaryCard
                    key={program.id}
                    program={program}
                    active={String(program.id) === String(activeProgramId)}
                    onClick={() => setActiveProgramId(String(program.id))}
                  />
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div className="flex flex-col gap-2 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                  <h2 className="text-base font-black text-slate-900 sm:text-lg">
                    {selectedProgram?.title || "Program Applicants"}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500 sm:text-sm">
                    {selectedProgram?.meta || "Selected program"} •{" "}
                    {visibleApplications.length} applicant(s) shown
                  </p>
                </div>

                <span className="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">
                  {selectedProgram?.total || 0} total in program
                </span>
              </div>

              {visibleApplications.length === 0 ? (
                <div className="p-8 text-center text-sm text-slate-500">
                  No applicants found for this program with the current filter.
                </div>
              ) : (
                <>
                  <div className="grid gap-3 p-4 md:hidden">
                    {visibleApplications.map((application) => (
                      <MobileApplicationCard
                        key={application.id}
                        application={application}
                        deletingId={deletingId}
                        onView={() =>
                          navigate(`/dashboard/applications/${application.id}`)
                        }
                        onDelete={() => handleDelete(application.id)}
                      />
                    ))}
                  </div>

                  <div className="hidden overflow-x-auto md:block">
                    <table className="w-full min-w-[980px]">
                      <thead className="border-b border-slate-200 bg-slate-50">
                        <tr>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Applicant
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Program
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Email
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Shift
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Experience
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Status
                          </th>
                          <th className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Submitted
                          </th>
                          <th className="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                            Actions
                          </th>
                        </tr>
                      </thead>

                      <tbody>
                        {visibleApplications.map((application) => (
                          <tr
                            key={application.id}
                            onClick={() =>
                              navigate(`/dashboard/applications/${application.id}`)
                            }
                            className="cursor-pointer border-b border-slate-100 transition hover:bg-slate-50"
                          >
                            <td className="px-3 py-3">
                              <div className="max-w-[180px] truncate text-sm font-bold text-slate-900">
                                {getApplicantName(application)}
                              </div>
                              <div className="mt-1 max-w-[180px] truncate text-xs text-slate-500">
                                {application?.applicant?.phone || "-"}
                              </div>
                            </td>

                            <td className="px-3 py-3">
                              <div className="max-w-[170px] truncate text-sm font-semibold text-slate-800">
                                {getProgramTitle(application)}
                              </div>
                              <div className="mt-1 max-w-[170px] truncate text-xs text-slate-500">
                                {getProgramMeta(application)}
                              </div>
                            </td>

                            <td className="px-3 py-3 text-sm text-slate-700">
                              <div className="max-w-[190px] truncate">
                                {application?.applicant?.email || "-"}
                              </div>
                            </td>

                            <td className="px-3 py-3 text-sm text-slate-700">
                              <div className="max-w-[130px] truncate">
                                {application?.shift?.name || "-"}
                              </div>
                            </td>

                            <td className="px-3 py-3 text-sm text-slate-700">
                              <div className="max-w-[130px] truncate">
                                {application?.experience_level || "-"}
                              </div>
                            </td>

                            <td className="px-3 py-3">
                              <StatusBadge status={application?.status} />
                            </td>

                            <td className="px-3 py-3 text-sm text-slate-700">
                              {formatDate(application?.submitted_at)}
                            </td>

                            <td className="px-3 py-3">
                              <div className="flex justify-end gap-2">
                                <button
                                  type="button"
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    navigate(
                                      `/dashboard/applications/${application.id}`
                                    );
                                  }}
                                  className="rounded-lg bg-indigo-50 px-3 py-2 text-[11px] font-bold text-indigo-700 transition hover:bg-indigo-100"
                                >
                                  View
                                </button>

                                <button
                                  type="button"
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    handleDelete(application.id);
                                  }}
                                  disabled={deletingId === application.id}
                                  className="rounded-lg bg-rose-50 px-3 py-2 text-[11px] font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                  {deletingId === application.id
                                    ? "Deleting..."
                                    : "Delete"}
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}