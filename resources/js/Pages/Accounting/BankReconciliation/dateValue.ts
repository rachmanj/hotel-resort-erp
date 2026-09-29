import dayjs, { type Dayjs } from 'dayjs';

/**
 * Ant Design DatePicker and RangePicker must receive `null` or `undefined` when empty.
 * Passing `dayjs('')` or any other invalid Dayjs makes the input show "Invalid Date" and
 * anchors the calendar panel on an invalid date so every cell renders as "Invalid".
 */
export function toDayjsOrNull(value?: string | null): Dayjs | null {
    if (value == null || value === '') {
        return null;
    }

    const parsed = dayjs(value);

    return parsed.isValid() ? parsed : null;
}

export function toDateString(value?: Dayjs | null): string {
    if (value == null || !value.isValid()) {
        return '';
    }

    return value.format('YYYY-MM-DD');
}

export function toRangePickerValue(
    from?: string | null,
    to?: string | null,
): [Dayjs, Dayjs] | undefined {
    const start = toDayjsOrNull(from);
    const end = toDayjsOrNull(to);

    if (start && end) {
        return [start, end];
    }

    return undefined;
}

export type DayjsRange = [Dayjs | null, Dayjs | null] | null;

export function sanitizeDayjsRange(values: DayjsRange): DayjsRange {
    if (!values) {
        return null;
    }

    const start = values[0]?.isValid() ? values[0] : null;
    const end = values[1]?.isValid() ? values[1] : null;

    if (!start && !end) {
        return null;
    }

    return [start, end];
}
