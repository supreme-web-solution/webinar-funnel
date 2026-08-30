export type GeoRule = { country: string; url: string };
export type DeviceRule = { device: string; url: string };

export function rulesToPayload(geoRules: GeoRule[], deviceRules: DeviceRule[]) {
    const geo: Record<string, string> = {};
    const device: Record<string, string> = {};

    for (const row of geoRules) {
        if (row.country && row.url.trim()) {
            geo[row.country] = row.url.trim();
        }
    }

    for (const row of deviceRules) {
        if (row.device && row.url.trim()) {
            device[row.device] = row.url.trim();
        }
    }

    return {
        geo_rules: Object.keys(geo).length ? geo : null,
        device_rules: Object.keys(device).length ? device : null,
    };
}

export function payloadToRules(
    geo: Record<string, string> | null | undefined,
    device: Record<string, string> | null | undefined,
): { geoRules: GeoRule[]; deviceRules: DeviceRule[] } {
    const geoRules = geo
        ? Object.entries(geo).map(([country, url]) => ({ country, url }))
        : [];
    const deviceRules = device
        ? Object.entries(device).map(([deviceKey, url]) => ({ device: deviceKey, url }))
        : [];

    return { geoRules, deviceRules };
}
