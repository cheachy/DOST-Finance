export const peso = (v: string | null) =>
    v == null
        ? ""
        : new Intl.NumberFormat("en-PH", { minimumFractionDigits: 2 }).format(
              Number(v),
          );

// map a status_flag label to a soft row tint
export const flagClass = (f: string | null) => {
    if (!f) return "";
    const u = f.toUpperCase();
    if (u.includes("CANCEL")) return "row--cancelled";
    if (u.includes("NYDD") && u.includes("NOT")) return "row--nydd";
    if (u.includes("NYDD") || u.includes("BECAME")) return "row--becamedd";
    if (u.includes("DD")) return "row--dd";
    if (u.includes("UNISSUED")) return "row--unissued";
    return "";
};
