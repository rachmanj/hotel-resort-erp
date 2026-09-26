import { Tag, theme } from 'antd';

interface StatusTagProps {
    label: string;
    tone?: 'default' | 'success' | 'warning' | 'error' | 'processing';
}

export default function StatusTag({ label, tone = 'default' }: StatusTagProps) {
    const { token } = theme.useToken();

    const colorMap = {
        default: token.colorTextSecondary,
        success: token.colorSuccess,
        warning: token.colorWarning,
        error: token.colorError,
        processing: token.colorInfo,
    };

    return (
        <Tag
            style={{
                marginInlineEnd: 0,
                borderColor: colorMap[tone],
                color: colorMap[tone],
                background: token.colorFillTertiary,
            }}
        >
            {label}
        </Tag>
    );
}
