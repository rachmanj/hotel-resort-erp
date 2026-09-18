import type { ReactNode } from 'react';
import { Carousel, Grid, theme, Typography } from 'antd';

export interface ResortSlide {
    src: string;
    alt?: string;
}

interface GuestLayoutProps {
    children: ReactNode;
    slides?: ResortSlide[];
}

export default function GuestLayout({ children, slides = [] }: GuestLayoutProps) {
    const { token } = theme.useToken();
    const screens = Grid.useBreakpoint();
    const hasSlides = slides.length > 0;
    const captionInset = screens.md ? 48 : 16;

    const cardSurface = `color-mix(in srgb, ${token.colorBgContainer} 82%, transparent)`;

    return (
        <>
            <style>{`
                .guest-layout {
                    position: relative;
                    min-height: 100vh;
                    width: 100%;
                    overflow-x: hidden;
                }
                .guest-layout__hero {
                    position: fixed;
                    inset: 0;
                    z-index: 0;
                    overflow: hidden;
                }
                .guest-layout__gradient {
                    position: fixed;
                    inset: 0;
                    z-index: 1;
                    pointer-events: none;
                    background: linear-gradient(
                        to top,
                        rgba(0, 0, 0, 0.65) 0%,
                        rgba(0, 0, 0, 0.15) 50%,
                        rgba(0, 0, 0, 0.35) 100%
                    );
                }
                .guest-layout__caption {
                    position: fixed;
                    z-index: 2;
                    pointer-events: none;
                    max-width: min(420px, calc(100vw - 32px));
                }
                .guest-layout__form {
                    position: relative;
                    z-index: 3;
                    min-height: 100vh;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                    box-sizing: border-box;
                }
                @media (min-width: 992px) {
                    .guest-layout__form {
                        justify-content: flex-end;
                        padding: 48px 64px;
                    }
                }
                .guest-layout__card {
                    width: 100%;
                    max-width: 420px;
                    min-width: 0;
                    box-sizing: border-box;
                    border-radius: ${token.borderRadiusLG}px;
                    border: 1px solid ${token.colorBorderSecondary};
                    box-shadow: ${token.boxShadowSecondary};
                    backdrop-filter: blur(16px);
                    -webkit-backdrop-filter: blur(16px);
                }
                .guest-layout__card .ant-card {
                    background: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                }
                .guest-carousel,
                .guest-carousel .slick-slider,
                .guest-carousel .slick-list,
                .guest-carousel .slick-track,
                .guest-carousel .slick-slide,
                .guest-carousel .slick-slide > div {
                    height: 100%;
                }
                .guest-carousel .slick-slide > div {
                    height: 100vh;
                }
                .guest-carousel__image {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }
            `}</style>
            <div
                className="guest-layout"
                style={{ background: hasSlides ? undefined : token.colorBgLayout }}
            >
                {hasSlides && (
                    <div className="guest-layout__hero">
                        <Carousel
                            className="guest-carousel"
                            autoplay
                            arrows
                            dots
                            speed={1400}
                            autoplaySpeed={6000}
                            style={{ height: '100vh' }}
                        >
                            {slides.map((slide) => (
                                <div key={slide.src}>
                                    <img
                                        className="guest-carousel__image"
                                        src={slide.src}
                                        alt={slide.alt ?? 'Pratasaba Resort'}
                                    />
                                </div>
                            ))}
                        </Carousel>
                    </div>
                )}
                {hasSlides && <div className="guest-layout__gradient" />}
                {hasSlides && (
                    <div
                        className="guest-layout__caption"
                        style={{
                            bottom: captionInset,
                            left: captionInset,
                        }}
                    >
                        <Typography.Title
                            level={screens.md ? 2 : 3}
                            style={{ color: '#fff', margin: 0, marginBottom: 8 }}
                        >
                            Pratasaba Resort
                        </Typography.Title>
                        <Typography.Paragraph
                            style={{
                                color: 'rgba(255,255,255,0.85)',
                                margin: 0,
                                fontSize: screens.md ? 16 : 14,
                            }}
                        >
                            Dive Resort and Spa, Maratua
                        </Typography.Paragraph>
                    </div>
                )}
                <div className="guest-layout__form">
                    <div
                        className="guest-layout__card"
                        style={{ background: cardSurface }}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
