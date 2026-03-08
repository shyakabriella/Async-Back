import { Link, Navigate, useLocation, useParams } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api";

const DETAIL_TABS = [
  { key: "overview", label: "Overview" },
  { key: "objectives", label: "Objectives" },
  { key: "modules", label: "Modules" },
  { key: "skills", label: "Skills" },
  { key: "outcomes", label: "Outcomes" },
  { key: "tools", label: "Tools" },
  { key: "shiftSettings", label: "Shift Settings" },
];

const samplePrograms = [
  {
    id: 1,
    code: "PRG-001",
    slug: "software-development",
    name: "Software Development",
    badge: "Web & Mobile",
    category: "Technology",
    duration: "12 Weeks",
    level: "Beginner to Intermediate",
    format: "Practical Training",
    status: "Active",
    instructor: "Jean Claude",
    students: 48,
    startDate: "2026-03-01",
    endDate: "2026-05-30",
    description:
      "This program helps learners build practical skills in frontend, backend, database design, APIs, deployment, and real-world software project development.",
    objectives: [
      "Understand software development fundamentals",
      "Build real frontend and backend projects",
      "Work with databases and APIs",
      "Learn team collaboration and deployment",
    ],
    modules: ["HTML & CSS", "JavaScript", "React", "Backend APIs", "Database"],
    skills: [
      "Frontend development",
      "Backend development",
      "API integration",
      "Version control",
    ],
    outcomes: [
      "Build complete web applications",
      "Understand real project workflow",
      "Prepare for internship roles",
    ],
    tools: ["React", "Node.js", "MySQL", "Git", "Tailwind CSS"],
    shifts: [
      {
        name: "Morning Shift",
        startTime: "08:00",
        endTime: "11:00",
        capacity: 25,
      },
      {
        name: "Afternoon Shift",
        startTime: "14:00",
        endTime: "17:00",
        capacity: 23,
      },
    ],
    image: "",
    intro: "",
    overview: "",
    icon_key: "",
    is_active: true,
  },
  {
    id: 2,
    code: "PRG-002",
    slug: "artificial-intelligence",
    name: "Artificial Intelligence",
    badge: "Smart Systems",
    category: "Technology",
    duration: "10 Weeks",
    level: "Intermediate",
    format: "Theory + Practice",
    status: "Active",
    instructor: "Aline Uwase",
    students: 35,
    startDate: "2026-03-04",
    endDate: "2026-05-20",
    description:
      "This program introduces students to AI concepts, intelligent systems, machine learning basics, and practical use of AI tools in real projects.",
    objectives: [
      "Learn the foundations of AI",
      "Understand machine learning basics",
      "Work with smart tools and datasets",
      "Apply AI thinking to digital solutions",
    ],
    modules: [
      "AI Fundamentals",
      "Machine Learning",
      "Data Basics",
      "Prompting",
      "AI Tools",
    ],
    skills: [
      "AI fundamentals",
      "Prompt engineering",
      "Data handling",
      "Automation concepts",
    ],
    outcomes: [
      "Understand key AI concepts",
      "Apply AI tools to real tasks",
      "Improve innovation thinking",
    ],
    tools: ["Python", "Datasets", "AI Platforms", "Automation Tools"],
    shifts: [
      {
        name: "Weekend Shift",
        startTime: "09:00",
        endTime: "12:00",
        capacity: 35,
      },
    ],
    image: "",
    intro: "",
    overview: "",
    icon_key: "",
    is_active: true,
  },
  {
    id: 3,
    code: "PRG-003",
    slug: "networking-essentials",
    name: "Networking Essentials",
    badge: "Infrastructure",
    category: "Infrastructure",
    duration: "8 Weeks",
    level: "Beginner to Intermediate",
    format: "Hands-on Lab Training",
    status: "Draft",
    instructor: "Eric Mugisha",
    students: 0,
    startDate: "2026-03-10",
    endDate: "2026-05-10",
    description:
      "This program provides practical skills in network setup, troubleshooting, routing, switching, and infrastructure support.",
    objectives: [
      "Understand network foundations",
      "Configure basic network devices",
      "Troubleshoot connectivity issues",
      "Support small and medium infrastructures",
    ],
    modules: [
      "Network Basics",
      "Routing",
      "Switching",
      "Troubleshooting",
      "Security Basics",
    ],
    skills: [
      "IP addressing",
      "Router configuration",
      "Switching",
      "Troubleshooting",
    ],
    outcomes: [
      "Set up small and medium networks",
      "Diagnose common connectivity issues",
      "Prepare for network support roles",
    ],
    tools: ["Routers", "Switches", "LAN", "WAN", "Testing Tools"],
    shifts: [],
    image: "",
    intro: "",
    overview: "",
    icon_key: "",
    is_active: true,
  },
  {
    id: 4,
    code: "PRG-004",
    slug: "cybersecurity-basics",
    name: "Cybersecurity Basics",
    badge: "Digital Safety",
    category: "Security",
    duration: "9 Weeks",
    level: "Beginner to Intermediate",
    format: "Practical + Guided Learning",
    status: "Archived",
    instructor: "Grace Ineza",
    students: 21,
    startDate: "2026-02-15",
    endDate: "2026-04-20",
    description:
      "This program introduces cybersecurity awareness, digital protection practices, risk management, and safe systems usage.",
    objectives: [
      "Learn core security concepts",
      "Understand common threats",
      "Apply safe digital practices",
      "Build awareness of security risks",
    ],
    modules: [
      "Security Fundamentals",
      "Threat Awareness",
      "Access Control",
      "Safe Practices",
      "Risk Basics",
    ],
    skills: [
      "Security awareness",
      "Safe access control",
      "Threat identification",
      "Risk basics",
    ],
    outcomes: [
      "Understand key security concepts",
      "Recognize common digital risks",
      "Improve system safety awareness",
    ],
    tools: ["Security Tools", "Protected Systems", "Risk Assessment"],
    shifts: [
      {
        name: "Evening Shift",
        startTime: "17:30",
        endTime: "20:00",
        capacity: 21,
      },
    ],
    image: "",
    intro: "",
    overview: "",
    icon_key: "",
    is_active: true,
  },
];

function safeArray(value) {
  if (Array.isArray(value)) return value;

  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) return parsed;
    } catch {
      return value
        .split("\n")
        .map((item) => item.trim())
        .filter(Boolean);
    }
  }

  return [];
}

function safeShiftArray(value) {
  let source = value;

  if (typeof source === "string") {
    try {
      source = JSON.parse(source);
    } catch {
      source = [];
    }
  }

  if (!Array.isArray(source)) return [];

  return source
    .map((item, index) => normalizeShift(item, index))
    .filter(
      (item) =>
        item.name || item.startTime || item.endTime || Number(item.capacity) > 0
    );
}

function normalizeShift(item, index = 0) {
  return {
    id: item?.id ?? `shift-${index + 1}`,
    name: item?.name || item?.shift_name || item?.title || "",
    startTime: item?.startTime || item?.start_time || "",
    endTime: item?.endTime || item?.end_time || "",
    capacity: Number(item?.capacity ?? item?.volume ?? item?.student_limit ?? 0),
  };
}

function toDateInput(value) {
  if (!value) return "";
  return String(value).slice(0, 10);
}

function formatDateLabel(value) {
  if (!value) return "-";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function formatTimeLabel(value) {
  if (!value) return "--:--";

  const raw = String(value).trim();
  const parts = raw.split(":");

  if (parts.length < 2) return raw;

  const hours = String(parts[0]).padStart(2, "0");
  const minutes = String(parts[1]).padStart(2, "0");

  return `${hours}:${minutes}`;
}

function getTotalShiftCapacity(shifts = []) {
  return shifts.reduce((sum, item) => sum + Number(item?.capacity || 0), 0);
}

function normalizeProgram(item) {
  if (!item) return null;

  return {
    id: item.id,
    code: item.code || "",
    slug: item.slug || "",
    name: item.name || "",
    badge: item.badge || "",
    category: item.category || "",
    duration: item.duration || "",
    level: item.level || "",
    format: item.format || "",
    status: item.status || "Draft",
    instructor: item.instructor || "",
    students: Number(item.students || 0),
    startDate: toDateInput(item.start_date || item.startDate),
    endDate: toDateInput(item.end_date || item.endDate),
    image: item.image || "",
    intro: item.intro || "",
    description: item.description || "",
    overview: item.overview || "",
    icon_key: item.icon_key || "",
    is_active:
      typeof item.is_active === "boolean" ? item.is_active : true,
    objectives: safeArray(item.objectives),
    modules: safeArray(item.modules),
    skills: safeArray(item.skills),
    outcomes: safeArray(item.outcomes),
    tools: safeArray(item.tools),
    shifts: safeShiftArray(item.shifts),
  };
}

function buildPayload(program) {
  return {
    slug: program.slug || "",
    name: program.name || "",
    badge: program.badge || "",
    category: program.category || "",
    duration: program.duration || "",
    level: program.level || "",
    format: program.format || "",
    status: program.status || "Draft",
    instructor: program.instructor || "",
    students: Number(program.students || 0),
    start_date: program.startDate || null,
    end_date: program.endDate || null,
    image: program.image || "",
    intro: program.intro || "",
    description: program.description || "",
    overview: program.overview || "",
    icon_key: program.icon_key || "",
    is_active: !!program.is_active,
    objectives: safeArray(program.objectives),
    modules: safeArray(program.modules),
    skills: safeArray(program.skills),
    outcomes: safeArray(program.outcomes),
    tools: safeArray(program.tools),
    shifts: safeShiftArray(program.shifts).map((shift) => ({
      name: shift.name || "",
      start_time: shift.startTime || "",
      end_time: shift.endTime || "",
      capacity: Number(shift.capacity || 0),
    })),
  };
}

export default function ProgramDetails() {
  const { id } = useParams();
  const location = useLocation();

  const fallbackProgram = useMemo(
    () =>
      normalizeProgram(
        location.state?.program ||
          samplePrograms.find((item) => String(item.id) === String(id))
      ),
    [id, location.state]
  );

  const [program, setProgram] = useState(fallbackProgram);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [activeTab, setActiveTab] = useState("overview");

  const [showEditProgram, setShowEditProgram] = useState(false);
  const [showObjectivesModal, setShowObjectivesModal] = useState(false);
  const [showModulesModal, setShowModulesModal] = useState(false);
  const [showSkillsModal, setShowSkillsModal] = useState(false);
  const [showOutcomesModal, setShowOutcomesModal] = useState(false);
  const [showToolsModal, setShowToolsModal] = useState(false);
  const [showShiftSettingsModal, setShowShiftSettingsModal] = useState(false);

  const token =
    localStorage.getItem("token") || sessionStorage.getItem("token");

  useEffect(() => {
    let ignore = false;

    async function fetchProgram() {
      try {
        setLoading(true);
        setError("");

        const response = await fetch(`${API_BASE_URL}/programs/${id}`, {
          headers: {
            Accept: "application/json",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        const text = await response.text();
        let result = {};

        try {
          result = text ? JSON.parse(text) : {};
        } catch {
          result = {};
        }

        if (!response.ok) {
          throw new Error(result?.message || "Failed to load program.");
        }

        const data = normalizeProgram({
          ...(fallbackProgram || {}),
          ...(result?.data || result),
        });

        if (!ignore) {
          setProgram(data);
        }
      } catch (err) {
        if (!ignore && !fallbackProgram) {
          setError(err.message || "Failed to load program.");
        }
      } finally {
        if (!ignore) {
          setLoading(false);
        }
      }
    }

    fetchProgram();

    return () => {
      ignore = true;
    };
  }, [id, token, fallbackProgram]);

  async function saveProgram(nextProgram) {
    if (!program) return;

    try {
      setSaving(true);
      setError("");

      const endpointKey = program.id || id;

      const response = await fetch(`${API_BASE_URL}/programs/${endpointKey}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(buildPayload(nextProgram)),
      });

      const text = await response.text();
      let result = {};

      try {
        result = text ? JSON.parse(text) : {};
      } catch {
        result = {};
      }

      if (!response.ok) {
        throw new Error(result?.message || "Failed to update program.");
      }

      setProgram(
        normalizeProgram({
          ...nextProgram,
          ...(result?.data || {}),
        })
      );
      closeAllModals();
    } catch (err) {
      setError(err.message || "Failed to update program.");
    } finally {
      setSaving(false);
    }
  }

  function closeAllModals() {
    setShowEditProgram(false);
    setShowObjectivesModal(false);
    setShowModulesModal(false);
    setShowSkillsModal(false);
    setShowOutcomesModal(false);
    setShowToolsModal(false);
    setShowShiftSettingsModal(false);
  }

  function handleProgramSave(values) {
    saveProgram({
      ...program,
      ...values,
    });
  }

  function handleListSave(field, items) {
    saveProgram({
      ...program,
      [field]: items,
    });
  }

  function handleShiftSave(items) {
    saveProgram({
      ...program,
      shifts: items,
    });
  }

  function handleCurrentTabEdit() {
    if (activeTab === "overview") setShowEditProgram(true);
    if (activeTab === "objectives") setShowObjectivesModal(true);
    if (activeTab === "modules") setShowModulesModal(true);
    if (activeTab === "skills") setShowSkillsModal(true);
    if (activeTab === "outcomes") setShowOutcomesModal(true);
    if (activeTab === "tools") setShowToolsModal(true);
    if (activeTab === "shiftSettings") setShowShiftSettingsModal(true);
  }

  if (!loading && !program && !error) {
    return <Navigate to="/dashboard/programs" replace />;
  }

  const shiftCount = program?.shifts?.length || 0;
  const totalShiftCapacity = getTotalShiftCapacity(program?.shifts || []);

  return (
    <div className="space-y-4 pb-6">
      {error ? (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
          {error}
        </div>
      ) : null}

      <div className="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <span className="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">
                {loading ? "Loading..." : program?.code || "Program"}
              </span>

              <StatusBadge status={program?.status} compact />

              {program?.badge ? (
                <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                  {program.badge}
                </span>
              ) : null}
            </div>

            <h1 className="mt-2 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
              {loading ? "Loading program..." : program?.name || "Program Details"}
            </h1>

            <p className="mt-2 max-w-4xl text-sm leading-6 text-slate-600">
              {program?.description || "No description available."}
            </p>
          </div>

          <div className="flex shrink-0 flex-wrap gap-2">
            <button
              onClick={() => setShowEditProgram(true)}
              className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
            >
              Edit Program
            </button>

            <Link
              to="/dashboard/programs"
              className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Back
            </Link>
          </div>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-8">
            <MiniStat label="Category" value={program?.category || "-"} />
            <MiniStat label="Duration" value={program?.duration || "-"} />
            <MiniStat label="Level" value={program?.level || "-"} />
            <MiniStat label="Format" value={program?.format || "-"} />
            <MiniStat label="Instructor" value={program?.instructor || "-"} />
            <MiniStat label="Students" value={program?.students ?? 0} />
            <MiniStat label="Shifts" value={shiftCount} />
            <MiniStat label="Capacity" value={totalShiftCapacity} />
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
              <div className="flex flex-wrap gap-2">
                {DETAIL_TABS.map((tab) => (
                  <button
                    key={tab.key}
                    onClick={() => setActiveTab(tab.key)}
                    className={`rounded-xl px-3 py-2 text-sm font-medium transition ${
                      activeTab === tab.key
                        ? "bg-slate-900 text-white"
                        : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                    }`}
                    type="button"
                  >
                    {tab.label}
                  </button>
                ))}
              </div>

              <button
                onClick={handleCurrentTabEdit}
                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                type="button"
              >
                Edit {DETAIL_TABS.find((tab) => tab.key === activeTab)?.label}
              </button>
            </div>

            <div className="p-4">
              {activeTab === "overview" ? (
                <OverviewPanel program={program} />
              ) : activeTab === "objectives" ? (
                <CompactListPanel
                  title="Program Objectives"
                  items={program?.objectives || []}
                  emptyText="No objectives available."
                />
              ) : activeTab === "modules" ? (
                <CompactListPanel
                  title="Program Modules"
                  items={program?.modules || []}
                  emptyText="No modules available."
                  numbered
                />
              ) : activeTab === "skills" ? (
                <CompactTagPanel
                  title="Program Skills"
                  items={program?.skills || []}
                  emptyText="No skills available."
                />
              ) : activeTab === "outcomes" ? (
                <CompactListPanel
                  title="Program Outcomes"
                  items={program?.outcomes || []}
                  emptyText="No outcomes available."
                />
              ) : activeTab === "tools" ? (
                <CompactTagPanel
                  title="Program Tools"
                  items={program?.tools || []}
                  emptyText="No tools available."
                />
              ) : (
                <ShiftSettingsPanel
                  shifts={program?.shifts || []}
                  emptyText="No shifts available."
                />
              )}
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-sm font-semibold text-slate-900">
                Program Summary
              </h2>
              <button
                onClick={() => setShowEditProgram(true)}
                className="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
              >
                Edit Info
              </button>
            </div>

            <div className="mt-4 space-y-2.5">
              <MetaRow label="Status">
                <StatusBadge status={program?.status} compact />
              </MetaRow>
              <MetaRow label="Code">{program?.code || "-"}</MetaRow>
              <MetaRow label="Start">{formatDateLabel(program?.startDate)}</MetaRow>
              <MetaRow label="End">{formatDateLabel(program?.endDate)}</MetaRow>
              <MetaRow label="Instructor">{program?.instructor || "-"}</MetaRow>
              <MetaRow label="Students">{program?.students ?? 0}</MetaRow>
              <MetaRow label="Shifts">{shiftCount}</MetaRow>
              <MetaRow label="Capacity">{totalShiftCapacity}</MetaRow>
              <MetaRow label="Active">{program?.is_active ? "Yes" : "No"}</MetaRow>
            </div>
          </div>

          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {program?.image ? (
              <img
                src={program.image}
                alt={program.name}
                className="h-[220px] w-full object-cover"
              />
            ) : (
              <div className="grid h-[220px] place-items-center bg-slate-50 text-sm text-slate-400">
                No program image
              </div>
            )}

            <div className="grid grid-cols-2 gap-3 border-t border-slate-200 p-4">
              <SmallInfoCard
                label="Badge"
                value={program?.badge || "Not set"}
              />
              <SmallInfoCard
                label="Icon Key"
                value={program?.icon_key || "Not set"}
              />
              <SmallInfoCard
                label="Slug"
                value={program?.slug || "Not set"}
              />
              <SmallInfoCard
                label="Format"
                value={program?.format || "Not set"}
              />
            </div>
          </div>
        </div>
      </div>

      <ProgramEditModal
        open={showEditProgram}
        program={program}
        saving={saving}
        onClose={() => setShowEditProgram(false)}
        onSave={handleProgramSave}
      />

      <ListEditorModal
        open={showObjectivesModal}
        title="Edit Program Objectives"
        fieldLabel="Objective"
        items={program?.objectives || []}
        saving={saving}
        onClose={() => setShowObjectivesModal(false)}
        onSave={(items) => handleListSave("objectives", items)}
      />

      <ListEditorModal
        open={showModulesModal}
        title="Edit Program Modules"
        fieldLabel="Module"
        items={program?.modules || []}
        saving={saving}
        onClose={() => setShowModulesModal(false)}
        onSave={(items) => handleListSave("modules", items)}
      />

      <ListEditorModal
        open={showSkillsModal}
        title="Edit Program Skills"
        fieldLabel="Skill"
        items={program?.skills || []}
        saving={saving}
        onClose={() => setShowSkillsModal(false)}
        onSave={(items) => handleListSave("skills", items)}
      />

      <ListEditorModal
        open={showOutcomesModal}
        title="Edit Program Outcomes"
        fieldLabel="Outcome"
        items={program?.outcomes || []}
        saving={saving}
        onClose={() => setShowOutcomesModal(false)}
        onSave={(items) => handleListSave("outcomes", items)}
      />

      <ListEditorModal
        open={showToolsModal}
        title="Edit Program Tools"
        fieldLabel="Tool"
        items={program?.tools || []}
        saving={saving}
        onClose={() => setShowToolsModal(false)}
        onSave={(items) => handleListSave("tools", items)}
      />

      <ShiftSettingsModal
        open={showShiftSettingsModal}
        shifts={program?.shifts || []}
        saving={saving}
        onClose={() => setShowShiftSettingsModal(false)}
        onSave={handleShiftSave}
      />
    </div>
  );
}

function OverviewPanel({ program }) {
  const focusItems = [
    ...(program?.skills || []),
    ...(program?.tools || []),
  ].filter(Boolean);

  return (
    <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
      <div className="space-y-4">
        <CompactTextCard
          title="Description"
          text={program?.description || "No description available."}
        />
        <CompactTextCard
          title="Intro"
          text={program?.intro || "No intro available."}
        />
        <CompactTextCard
          title="Overview"
          text={program?.overview || "No overview available."}
        />
      </div>

      <div className="space-y-4">
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div className="text-sm font-semibold text-slate-900">
            Quick Snapshot
          </div>

          <div className="mt-3 grid grid-cols-2 gap-3">
            <SmallInfoCard
              label="Start"
              value={formatDateLabel(program?.startDate)}
            />
            <SmallInfoCard
              label="End"
              value={formatDateLabel(program?.endDate)}
            />
            <SmallInfoCard
              label="Level"
              value={program?.level || "-"}
            />
            <SmallInfoCard
              label="Format"
              value={program?.format || "-"}
            />
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-4">
          <div className="text-sm font-semibold text-slate-900">
            Learning Focus
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            {focusItems.length ? (
              focusItems.map((item, index) => (
                <span
                  key={`${item}-${index}`}
                  className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700"
                >
                  {item}
                </span>
              ))
            ) : (
              <p className="text-sm text-slate-500">No focus items available.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function ShiftSettingsPanel({ shifts, emptyText }) {
  const totalCapacity = getTotalShiftCapacity(shifts);

  if (!shifts.length) {
    return <EmptyBlock text={emptyText} compact />;
  }

  return (
    <div className="space-y-4">
      <div className="grid gap-3 md:grid-cols-3">
        <SmallInfoCard label="Total Shifts" value={shifts.length} />
        <SmallInfoCard label="Total Capacity" value={totalCapacity} />
        <SmallInfoCard
          label="Average Capacity"
          value={Math.round(totalCapacity / shifts.length || 0)}
        />
      </div>

      <div className="grid gap-3 lg:grid-cols-2">
        {shifts.map((shift, index) => (
          <div
            key={shift.id || `${shift.name}-${index}`}
            className="rounded-2xl border border-slate-200 bg-slate-50 p-4"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">
                  {shift.name || `Shift ${index + 1}`}
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                  Start: {formatTimeLabel(shift.startTime)} · End:{" "}
                  {formatTimeLabel(shift.endTime)}
                </p>
              </div>

              <span className="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">
                {Number(shift.capacity || 0)} students
              </span>
            </div>

            <div className="mt-4 grid grid-cols-3 gap-3">
              <SmallInfoCard
                label="Shift"
                value={shift.name || `Shift ${index + 1}`}
              />
              <SmallInfoCard
                label="Start Time"
                value={formatTimeLabel(shift.startTime)}
              />
              <SmallInfoCard
                label="End Time"
                value={formatTimeLabel(shift.endTime)}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function CompactListPanel({ title, items, emptyText, numbered = false }) {
  if (!items.length) {
    return <EmptyBlock text={emptyText} compact />;
  }

  return (
    <div>
      <div className="mb-3 text-sm font-semibold text-slate-900">{title}</div>

      <div className="grid gap-2 md:grid-cols-2">
        {items.map((item, index) => (
          <div
            key={`${item}-${index}`}
            className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
          >
            <span className="mt-0.5 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-white text-[11px] font-semibold text-slate-600 shadow-sm">
              {numbered ? index + 1 : "•"}
            </span>
            <p className="text-sm leading-5 text-slate-700">{item}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function CompactTagPanel({ title, items, emptyText }) {
  if (!items.length) {
    return <EmptyBlock text={emptyText} compact />;
  }

  return (
    <div>
      <div className="mb-3 text-sm font-semibold text-slate-900">{title}</div>

      <div className="flex flex-wrap gap-2">
        {items.map((item, index) => (
          <span
            key={`${item}-${index}`}
            className="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
          >
            {item}
          </span>
        ))}
      </div>
    </div>
  );
}

function CompactTextCard({ title, text }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4">
      <div className="text-sm font-semibold text-slate-900">{title}</div>
      <p className="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-600">
        {text}
      </p>
    </div>
  );
}

function MiniStat({ label, value }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
        {label}
      </div>
      <div className="mt-1.5 break-words text-sm font-semibold text-slate-900">
        {value}
      </div>
    </div>
  );
}

function SmallInfoCard({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
      <div className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">
        {label}
      </div>
      <div className="mt-1 break-words text-sm font-medium text-slate-800">
        {value}
      </div>
    </div>
  );
}

function MetaRow({ label, children }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
      <span className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
        {label}
      </span>
      <span className="max-w-[60%] break-words text-right text-sm font-medium text-slate-800">
        {children}
      </span>
    </div>
  );
}

function EmptyBlock({ text, compact = false }) {
  return (
    <div
      className={`rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-slate-500 ${
        compact ? "p-4 text-sm" : "p-5 text-sm"
      }`}
    >
      {text}
    </div>
  );
}

function StatusBadge({ status, compact = false }) {
  const styles =
    status === "Active"
      ? "border-emerald-200 bg-emerald-50 text-emerald-700"
      : status === "Draft"
      ? "border-amber-200 bg-amber-50 text-amber-700"
      : "border-slate-200 bg-slate-100 text-slate-700";

  return (
    <span
      className={`inline-flex items-center rounded-full border font-semibold ${styles} ${
        compact ? "px-2.5 py-1 text-[11px]" : "px-3 py-1 text-xs"
      }`}
    >
      {status || "Draft"}
    </span>
  );
}

function ModalShell({ open, title, children, onClose }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <div
        onClick={onClose}
        className="absolute inset-0 bg-slate-900/45 backdrop-blur-sm"
      />
      <div className="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 sm:px-5">
          <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
          <button
            onClick={onClose}
            className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50"
            type="button"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path
                d="M6 6l12 12M18 6 6 18"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
              />
            </svg>
          </button>
        </div>

        <div className="overflow-y-auto p-4 sm:p-5">{children}</div>
      </div>
    </div>
  );
}

function ProgramEditModal({ open, program, saving, onClose, onSave }) {
  const [form, setForm] = useState({
    slug: "",
    name: "",
    badge: "",
    category: "",
    duration: "",
    level: "",
    format: "",
    status: "Draft",
    instructor: "",
    students: "",
    startDate: "",
    endDate: "",
    image: "",
    intro: "",
    description: "",
    overview: "",
    icon_key: "",
    is_active: true,
  });

  useEffect(() => {
    if (program && open) {
      setForm({
        slug: program.slug || "",
        name: program.name || "",
        badge: program.badge || "",
        category: program.category || "",
        duration: program.duration || "",
        level: program.level || "",
        format: program.format || "",
        status: program.status || "Draft",
        instructor: program.instructor || "",
        students: String(program.students ?? ""),
        startDate: program.startDate || "",
        endDate: program.endDate || "",
        image: program.image || "",
        intro: program.intro || "",
        description: program.description || "",
        overview: program.overview || "",
        icon_key: program.icon_key || "",
        is_active: !!program.is_active,
      });
    }
  }, [program, open]);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]:
        type === "checkbox"
          ? checked
          : name === "students"
          ? value.replace(/\D/g, "")
          : value,
    }));
  }

  function handleImageUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onloadend = () => {
      setForm((prev) => ({
        ...prev,
        image: reader.result || "",
      }));
    };
    reader.readAsDataURL(file);
  }

  function removeImage() {
    setForm((prev) => ({
      ...prev,
      image: "",
    }));
  }

  function handleSubmit(e) {
    e.preventDefault();

    onSave({
      ...program,
      ...form,
      students: Number(form.students || 0),
    });
  }

  return (
    <ModalShell open={open} title="Edit Program Information" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
          Program code is generated automatically and cannot be edited here.
          <span className="ml-2 font-semibold">{program?.code || "-"}</span>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-4">
          <div className="flex flex-col gap-4 lg:flex-row">
            <div className="w-full lg:w-72">
              <label className="mb-2 block text-sm font-medium text-slate-700">
                Program Image
              </label>

              <div className="overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50">
                {form.image ? (
                  <img
                    src={form.image}
                    alt="Program preview"
                    className="h-48 w-full object-cover"
                  />
                ) : (
                  <div className="grid h-48 place-items-center px-4 text-center text-sm text-slate-400">
                    No image selected
                  </div>
                )}
              </div>

              <div className="mt-3 flex flex-wrap gap-2">
                <label className="inline-flex cursor-pointer items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
                  Upload Image
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleImageUpload}
                    className="hidden"
                    disabled={saving}
                  />
                </label>

                {form.image ? (
                  <button
                    type="button"
                    onClick={removeImage}
                    disabled={saving}
                    className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    Remove
                  </button>
                ) : null}
              </div>

              <p className="mt-2 text-xs text-slate-500">
                Upload a new image to replace the current one.
              </p>
            </div>

            <div className="flex-1">
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Field
                  label="Program Name"
                  name="name"
                  value={form.name}
                  onChange={handleChange}
                />
                <Field
                  label="Slug"
                  name="slug"
                  value={form.slug}
                  onChange={handleChange}
                />
                <Field
                  label="Badge"
                  name="badge"
                  value={form.badge}
                  onChange={handleChange}
                />
                <Field
                  label="Category"
                  name="category"
                  value={form.category}
                  onChange={handleChange}
                />
                <Field
                  label="Duration"
                  name="duration"
                  value={form.duration}
                  onChange={handleChange}
                />
                <Field
                  label="Level"
                  name="level"
                  value={form.level}
                  onChange={handleChange}
                />
                <Field
                  label="Format"
                  name="format"
                  value={form.format}
                  onChange={handleChange}
                />
                <Field
                  label="Instructor"
                  name="instructor"
                  value={form.instructor}
                  onChange={handleChange}
                />
                <Field
                  label="Students"
                  name="students"
                  value={form.students}
                  onChange={handleChange}
                />
                <Field
                  label="Start Date"
                  name="startDate"
                  type="date"
                  value={form.startDate}
                  onChange={handleChange}
                />
                <Field
                  label="End Date"
                  name="endDate"
                  type="date"
                  value={form.endDate}
                  onChange={handleChange}
                />
                <Field
                  label="Icon Key"
                  name="icon_key"
                  value={form.icon_key}
                  onChange={handleChange}
                />

                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Status
                  </label>
                  <select
                    name="status"
                    value={form.status}
                    onChange={handleChange}
                    className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-400"
                  >
                    <option>Active</option>
                    <option>Draft</option>
                    <option>Archived</option>
                  </select>
                </div>

                <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                  <input
                    type="checkbox"
                    name="is_active"
                    checked={form.is_active}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <span className="text-sm font-medium text-slate-700">
                    Program Active
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <TextAreaField
            label="Intro"
            name="intro"
            value={form.intro}
            onChange={handleChange}
            rows={3}
          />

          <TextAreaField
            label="Description"
            name="description"
            value={form.description}
            onChange={handleChange}
            rows={4}
          />

          <TextAreaField
            label="Overview"
            name="overview"
            value={form.overview}
            onChange={handleChange}
            rows={4}
          />
        </div>

        <div className="flex flex-wrap gap-2 pt-1">
          <button
            type="submit"
            disabled={saving}
            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
          >
            {saving ? "Saving..." : "Save Program"}
          </button>

          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </ModalShell>
  );
}

function ListEditorModal({
  open,
  title,
  fieldLabel,
  items,
  saving,
  onClose,
  onSave,
}) {
  const [rows, setRows] = useState([""]);

  useEffect(() => {
    if (open) {
      setRows(items.length ? items : [""]);
    }
  }, [items, open]);

  function handleChange(index, value) {
    setRows((prev) => prev.map((item, i) => (i === index ? value : item)));
  }

  function addRow() {
    setRows((prev) => [...prev, ""]);
  }

  function removeRow(index) {
    setRows((prev) => prev.filter((_, i) => i !== index));
  }

  function handleSubmit(e) {
    e.preventDefault();
    const cleaned = rows.map((item) => item.trim()).filter(Boolean);
    onSave(cleaned);
  }

  return (
    <ModalShell open={open} title={title} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-3">
        {rows.map((item, index) => (
          <div key={index} className="flex gap-2">
            <input
              type="text"
              value={item}
              onChange={(e) => handleChange(index, e.target.value)}
              placeholder={`${fieldLabel} ${index + 1}`}
              className="flex-1 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-400"
            />
            <button
              type="button"
              onClick={() => removeRow(index)}
              className="rounded-xl bg-rose-50 px-3 py-2.5 text-sm font-semibold text-rose-600 transition hover:bg-rose-100"
            >
              Remove
            </button>
          </div>
        ))}

        <button
          type="button"
          onClick={addRow}
          className="rounded-xl bg-indigo-50 px-3.5 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100"
        >
          + Add {fieldLabel}
        </button>

        <div className="flex flex-wrap gap-2 pt-2">
          <button
            type="submit"
            disabled={saving}
            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
          >
            {saving ? "Saving..." : "Save Changes"}
          </button>

          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </ModalShell>
  );
}

function ShiftSettingsModal({
  open,
  shifts,
  saving,
  onClose,
  onSave,
}) {
  const [rows, setRows] = useState([
    { name: "", startTime: "", endTime: "", capacity: "" },
  ]);

  useEffect(() => {
    if (open) {
      setRows(
        shifts.length
          ? shifts.map((item) => ({
              name: item.name || "",
              startTime: item.startTime || "",
              endTime: item.endTime || "",
              capacity: String(item.capacity ?? ""),
            }))
          : [{ name: "", startTime: "", endTime: "", capacity: "" }]
      );
    }
  }, [shifts, open]);

  function handleChange(index, field, value) {
    setRows((prev) =>
      prev.map((item, i) =>
        i === index
          ? {
              ...item,
              [field]: field === "capacity" ? value.replace(/\D/g, "") : value,
            }
          : item
      )
    );
  }

  function addRow() {
    setRows((prev) => [
      ...prev,
      { name: "", startTime: "", endTime: "", capacity: "" },
    ]);
  }

  function removeRow(index) {
    setRows((prev) => prev.filter((_, i) => i !== index));
  }

  function handleSubmit(e) {
    e.preventDefault();

    const cleaned = rows
      .map((item) => ({
        name: item.name.trim(),
        startTime: item.startTime,
        endTime: item.endTime,
        capacity: Number(item.capacity || 0),
      }))
      .filter(
        (item) =>
          item.name || item.startTime || item.endTime || item.capacity > 0
      );

    onSave(cleaned);
  }

  return (
    <ModalShell open={open} title="Shift Settings" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
          Create available shifts for this program. Each shift should have a
          name, start time, end time, and student volume/capacity.
        </div>

        <div className="space-y-3">
          {rows.map((row, index) => (
            <div
              key={index}
              className="rounded-2xl border border-slate-200 bg-slate-50 p-4"
            >
              <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-900">
                  Shift {index + 1}
                </h3>

                <button
                  type="button"
                  onClick={() => removeRow(index)}
                  className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100"
                >
                  Remove
                </button>
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Field
                  label="Shift Name"
                  value={row.name}
                  onChange={(e) =>
                    handleChange(index, "name", e.target.value)
                  }
                  placeholder="Morning Shift"
                />

                <Field
                  label="Start Time"
                  type="time"
                  value={row.startTime}
                  onChange={(e) =>
                    handleChange(index, "startTime", e.target.value)
                  }
                />

                <Field
                  label="End Time"
                  type="time"
                  value={row.endTime}
                  onChange={(e) =>
                    handleChange(index, "endTime", e.target.value)
                  }
                />

                <Field
                  label="Volume / Capacity"
                  value={row.capacity}
                  onChange={(e) =>
                    handleChange(index, "capacity", e.target.value)
                  }
                  placeholder="30"
                />
              </div>
            </div>
          ))}
        </div>

        <button
          type="button"
          onClick={addRow}
          className="rounded-xl bg-indigo-50 px-3.5 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100"
        >
          + Add Shift
        </button>

        <div className="flex flex-wrap gap-2 pt-2">
          <button
            type="submit"
            disabled={saving}
            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
          >
            {saving ? "Saving..." : "Save Shift Settings"}
          </button>

          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </ModalShell>
  );
}

function Field({ label, ...props }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-slate-700">
        {label}
      </label>
      <input
        {...props}
        className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-400"
      />
    </div>
  );
}

function TextAreaField({ label, ...props }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-slate-700">
        {label}
      </label>
      <textarea
        {...props}
        className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-400"
      />
    </div>
  );
}